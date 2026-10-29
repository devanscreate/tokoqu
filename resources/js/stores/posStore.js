import { defineStore } from 'pinia';
import { db } from '../db/dexie';
import { apiRequest } from '../utils/api';
import { calculateCartTotals, generateUuid, getTodayYymmdd } from '../utils/format';

export const usePosStore = defineStore('pos', {
    state: () => ({
        user: null,
        sessionExpired: false,
        currentShift: null,
        isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
        isSyncing: false,
        lastCatalogSyncAt: localStorage.getItem('pos_last_catalog_sync') || null,

        settings: {
            store_name: 'TOKOQU RETAIL',
            store_address: '',
            store_phone: '',
            timezone: 'Asia/Jakarta',
            receipt_footer: 'Terima kasih telah berbelanja.',
            tax_mode: 'off',
            tax_rate: '11',
            return_window_days: '7',
            cashier_discount_limit_pct: '10',
            allow_negative_stock: '0',
        },
        registers: [],
        categories: [],
        products: [],
        heldCarts: [],
        outboxItems: [],

        // Active Cart State (W4)
        cart: {
            client_uuid: null,
            items: [],
            order_discount_type: 'amount',
            order_discount_value: 0,
            approval_token: null,
            approved_by_name: null,
        },
        resumeNotices: [],
        priceChangeAlert: null,
        activeReceipt: null,
    }),

    getters: {
        activeProducts(state) {
            return state.products.filter((p) => p.is_active && !p.deleted_at);
        },

        pendingOutboxCount(state) {
            return state.outboxItems.filter((i) => i.status === 'pending').length;
        },

        needsAttentionOutbox(state) {
            return state.outboxItems.filter((i) => i.status === 'needs_attention');
        },

        cartTotals(state) {
            if (!state.cart.items.length) {
                return {
                    lines: [],
                    subtotal: 0,
                    line_discount_total: 0,
                    order_discount_amount: 0,
                    discount_total: 0,
                    taxable_base: 0,
                    tax_mode: state.settings.tax_mode || 'off',
                    tax_rate: Number(state.settings.tax_rate || 0),
                    tax_total: 0,
                    grand_total: 0,
                };
            }
            return calculateCartTotals(state.cart.items, {
                order_discount_type: state.cart.order_discount_type,
                order_discount_value: state.cart.order_discount_value,
                tax_mode: state.settings.tax_mode || 'off',
                tax_rate: Number(state.settings.tax_rate || 0),
            });
        },

        requiresDiscountPin(state) {
            if (!state.cart.items.length) return false;
            if (state.user && (state.user.role === 'manager' || state.user.role === 'owner')) {
                return false;
            }
            const limitPct = Number(state.settings.cashier_discount_limit_pct || 10);
            const totals = this.cartTotals;

            for (const line of totals.lines) {
                if (line.gross_line > 0) {
                    const pct = (line.line_discount / line.gross_line) * 100;
                    if (pct > limitPct + 0.0001) return true;
                }
            }
            if (totals.subtotal > 0) {
                const orderPct = (totals.order_discount_amount / totals.subtotal) * 100;
                if (orderPct > limitPct + 0.0001) return true;
            }
            return false;
        },
    },

    actions: {
        async initApp() {
            window.addEventListener('online', () => {
                this.isOnline = true;
                this.syncCatalog();
                this.syncOutbox();
            });
            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            await this.loadFromIndexedDb();

            const savedShift = localStorage.getItem('pos_current_shift');
            if (savedShift) {
                try {
                    this.currentShift = JSON.parse(savedShift);
                } catch {
                    this.currentShift = null;
                }
            }

            try {
                await this.fetchMe();
                await this.syncCatalog();
                await this.syncOutbox();
            } catch {
                // Offline or unauthenticated startup
            }
        },

        async loadFromIndexedDb() {
            const [products, categories, registers, settingsRows, heldCarts, outbox] = await Promise.all([
                db.products.toArray(),
                db.categories.orderBy('sort_order').toArray(),
                db.registers.toArray(),
                db.settings.toArray(),
                db.held_carts.toArray(),
                db.outbox.toArray(),
            ]);

            if (products.length) this.products = products;
            if (categories.length) this.categories = categories;
            if (registers.length) this.registers = registers;
            if (settingsRows.length) {
                const map = {};
                settingsRows.forEach((r) => {
                    map[r.key] = r.value;
                });
                this.settings = { ...this.settings, ...map };
            }
            this.heldCarts = heldCarts;
            this.outboxItems = outbox;
        },

        async fetchMe() {
            const data = await apiRequest('/me');
            this.user = data.user;
            this.sessionExpired = false;
            this.currentShift = data.current_shift;
            if (this.currentShift) {
                localStorage.setItem('pos_current_shift', JSON.stringify(this.currentShift));
            } else {
                localStorage.removeItem('pos_current_shift');
            }
            return data;
        },

        async login(email, password) {
            const data = await apiRequest('/auth/login', {
                method: 'POST',
                body: { email, password },
            });
            this.user = data.user;
            this.sessionExpired = false;
            this.currentShift = data.current_shift;
            if (this.currentShift) {
                localStorage.setItem('pos_current_shift', JSON.stringify(this.currentShift));
            }
            await this.syncCatalog(true);
            await this.syncOutbox();
            return data;
        },

        async logout() {
            if (this.pendingOutboxCount > 0) {
                throw new Error(
                    `Masih ada ${this.pendingOutboxCount} penjualan offline yang belum disinkronisasi. Sinkronkan terlebih dahulu agar data tidak hilang (Section 14).`
                );
            }
            try {
                await apiRequest('/auth/logout', { method: 'POST' });
            } catch {
                // Ignore network failure on logout
            }
            this.user = null;
            this.currentShift = null;
            localStorage.removeItem('pos_current_shift');
        },

        async syncCatalog(forceFull = false) {
            if (!this.isOnline || !this.user) return;
            try {
                const sinceParam = !forceFull && this.lastCatalogSyncAt ? `?since=${encodeURIComponent(this.lastCatalogSyncAt)}` : '';
                const data = await apiRequest(`/catalog${sinceParam}`);

                if (data.settings) {
                    this.settings = { ...this.settings, ...data.settings };
                    const rows = Object.entries(data.settings).map(([key, value]) => ({ key, value }));
                    await db.settings.bulkPut(rows);
                }

                if (data.registers) {
                    this.registers = data.registers;
                    await db.registers.clear();
                    await db.registers.bulkPut(data.registers);
                }

                if (data.categories) {
                    this.categories = data.categories;
                    await db.categories.clear();
                    await db.categories.bulkPut(data.categories);
                }

                if (data.products && data.products.length) {
                    if (forceFull || !this.lastCatalogSyncAt) {
                        await db.products.clear();
                    }
                    await db.products.bulkPut(data.products);
                    this.products = await db.products.toArray();
                }

                // Sync invoice sequence counters per register for today (W13)
                if (data.invoice_sequences) {
                    const yymmdd = getTodayYymmdd(this.settings.timezone || 'Asia/Jakarta');
                    for (const [regId, serverSeq] of Object.entries(data.invoice_sequences)) {
                        await this.ensureLocalInvoiceCounter(Number(regId), yymmdd, Number(serverSeq));
                    }
                }

                this.lastCatalogSyncAt = data.server_time;
                localStorage.setItem('pos_last_catalog_sync', data.server_time);
            } catch (err) {
                if (err.status === 401) {
                    this.sessionExpired = true;
                }
            }
        },

        async ensureLocalInvoiceCounter(registerId, yymmdd, serverSeq) {
            const key = `${registerId}_${yymmdd}`;
            const existing = await db.invoice_counters.get(key);
            const highest = Math.max(existing?.seq || 0, serverSeq || 0);
            await db.invoice_counters.put({ key, seq: highest });
            return highest;
        },

        async nextLocalInvoiceNo(registerId, registerCode) {
            const yymmdd = getTodayYymmdd(this.settings.timezone || 'Asia/Jakarta');
            const key = `${registerId}_${yymmdd}`;
            const existing = await db.invoice_counters.get(key);
            const nextSeq = (existing?.seq || 0) + 1;
            await db.invoice_counters.put({ key, seq: nextSeq });
            return `${registerCode}-${yymmdd}-${String(nextSeq).padStart(4, '0')}`;
        },

        // W4.1: Add product to cart; generate client_uuid when first item is added
        addToCart(product, qtyToAdd = 1) {
            if (!product.is_active) return;

            if (!this.cart.client_uuid || this.cart.items.length === 0) {
                this.cart.client_uuid = generateUuid();
            }

            const existingIdx = this.cart.items.findIndex((i) => i.product_id === product.id);
            if (existingIdx >= 0) {
                const currentQty = Number(this.cart.items[existingIdx].qty);
                const nextQty = Number((currentQty + qtyToAdd).toFixed(3));
                this.cart.items[existingIdx].qty = nextQty;
            } else {
                this.cart.items.push({
                    product_id: product.id,
                    sku: product.sku,
                    name: product.name,
                    qty: Number(qtyToAdd),
                    unit_price: Number(product.price),
                    unit_cost: Number(product.cost || 0),
                    line_discount: 0,
                    track_stock: Boolean(product.track_stock),
                    stock_qty: Number(product.stock_qty || 0),
                    allow_decimal_qty: Boolean(product.allow_decimal_qty),
                });
            }
        },

        updateCartLineQty(index, newQty) {
            const item = this.cart.items[index];
            if (!item) return;
            let parsed = Number(newQty);
            if (Number.isNaN(parsed) || parsed <= 0) {
                this.cart.items.splice(index, 1);
                if (this.cart.items.length === 0) {
                    this.clearCart();
                }
                return;
            }
            if (!item.allow_decimal_qty) {
                parsed = Math.round(parsed);
            } else {
                parsed = Number(parsed.toFixed(3));
            }
            item.qty = parsed;
        },

        updateCartLineDiscount(index, discountAmount) {
            const item = this.cart.items[index];
            if (!item) return;
            const maxLine = Math.round(item.qty * item.unit_price);
            item.line_discount = Math.min(maxLine, Math.max(0, Math.round(Number(discountAmount || 0))));
            this.cart.approval_token = null;
            this.cart.approved_by_name = null;
        },

        clearCart() {
            this.cart = {
                client_uuid: null,
                items: [],
                order_discount_type: 'amount',
                order_discount_value: 0,
                approval_token: null,
                approved_by_name: null,
            };
            this.priceChangeAlert = null;
            this.resumeNotices = [];
        },

        // W5.1: Hold cart locally on device
        async holdCurrentCart(label = '') {
            if (!this.cart.items.length || !this.currentShift) return;
            const held = {
                id: this.cart.client_uuid || generateUuid(),
                register_id: this.currentShift.register_id,
                label: label.trim() || `Antrean #${this.heldCarts.length + 1}`,
                cart_snapshot: JSON.parse(JSON.stringify(this.cart)),
                created_at: new Date().toISOString(),
            };
            await db.held_carts.put(held);
            this.heldCarts = await db.held_carts.toArray();
            this.clearCart();
        },

        // W5.2: Resume held cart, re-price from current catalog & re-check stock
        async resumeHeldCart(heldId) {
            const held = await db.held_carts.get(heldId);
            if (!held) return;

            const notices = [];
            const restoredItems = [];

            for (const item of held.cart_snapshot.items) {
                const latestProduct = this.products.find((p) => p.id === item.product_id);
                if (!latestProduct || !latestProduct.is_active || latestProduct.deleted_at) {
                    notices.push(`Produk "${item.name}" sudah tidak aktif dan dihapus dari keranjang.`);
                    continue;
                }

                if (Number(latestProduct.price) !== Number(item.unit_price)) {
                    notices.push(
                        `Harga "${latestProduct.name}" diperbarui dari Rp ${item.unit_price.toLocaleString('id-ID')} menjadi Rp ${Number(latestProduct.price).toLocaleString('id-ID')} (W5.2).`
                    );
                }

                if (latestProduct.track_stock && Number(latestProduct.stock_qty) < Number(item.qty)) {
                    notices.push(
                        `Perhatian: Stok "${latestProduct.name}" saat ini tinggal ${latestProduct.stock_qty} (diminta ${item.qty}).`
                    );
                }

                restoredItems.push({
                    ...item,
                    name: latestProduct.name,
                    sku: latestProduct.sku,
                    unit_price: Number(latestProduct.price),
                    unit_cost: Number(latestProduct.cost || 0),
                    stock_qty: Number(latestProduct.stock_qty || 0),
                });
            }

            this.cart = {
                ...held.cart_snapshot,
                items: restoredItems,
            };
            this.resumeNotices = notices;

            await db.held_carts.delete(heldId);
            this.heldCarts = await db.held_carts.toArray();
        },

        async discardHeldCart(heldId) {
            await db.held_carts.delete(heldId);
            this.heldCarts = await db.held_carts.toArray();
        },

        // W4.6 & W13: Confirm Checkout (Online first, fallback to Offline Outbox if offline/network lost)
        async confirmCheckout(payments) {
            if (!this.currentShift) {
                throw new Error('Tidak ada shift terbuka. Buka shift terlebih dahulu (W3).');
            }
            if (!this.cart.items.length) {
                throw new Error('Keranjang belanja masih kosong.');
            }
            if (this.requiresDiscountPin && !this.cart.approval_token) {
                throw new Error('Diskon melebihi batas kasir. Otorisasi PIN Manager diperlukan.');
            }

            const totals = this.cartTotals;
            const clientUuid = this.cart.client_uuid || generateUuid();

            const payload = {
                client_uuid: clientUuid,
                shift_id: this.currentShift.id,
                items: totals.lines.map((l) => ({
                    product_id: l.product_id,
                    name: l.name,
                    sku: l.sku,
                    qty: l.qty,
                    unit_price: l.unit_price,
                    unit_cost: l.unit_cost,
                    line_discount: l.line_discount,
                })),
                order_discount_type: this.cart.order_discount_type,
                order_discount_value: this.cart.order_discount_value,
                order_discount_amount: totals.order_discount_amount,
                tax_mode: totals.tax_mode,
                tax_rate: totals.tax_rate,
                expected_grand_total: totals.grand_total,
                approval_token: this.cart.approval_token,
                payments,
                created_at: new Date().toISOString(),
            };

            if (this.isOnline) {
                try {
                    const response = await apiRequest('/sales', {
                        method: 'POST',
                        body: payload,
                    });

                    // Sync local stock cache
                    for (const soldLine of response.sale.items) {
                        const prod = this.products.find((p) => p.id === soldLine.product_id);
                        if (prod && prod.track_stock) {
                            prod.stock_qty = Number((Number(prod.stock_qty) - Number(soldLine.qty)).toFixed(3));
                            await db.products.put({ ...prod });
                        }
                    }

                    // Keep local invoice sequence up to date
                    const m = response.sale.invoice_no.match(/-(\d+)$/);
                    if (m) {
                        const yymmdd = getTodayYymmdd(this.settings.timezone || 'Asia/Jakarta');
                        await this.ensureLocalInvoiceCounter(this.currentShift.register_id, yymmdd, Number(m[1]));
                    }

                    this.activeReceipt = {
                        ...response.sale,
                        is_copy: false,
                    };
                    this.clearCart();
                    return response.sale;
                } catch (err) {
                    if (err.status === 401) {
                        this.sessionExpired = true;
                        throw new Error('Sesi login telah berakhir. Silakan masuk kembali—keranjang Anda tetap tersimpan (W2).');
                    }
                    // R12: HTTP 409 price changed on server
                    if (err.status === 409 && err.data?.fresh_totals) {
                        const fresh = err.data.fresh_totals;
                        fresh.lines.forEach((fl) => {
                            const target = this.cart.items.find((ci) => ci.product_id === fl.product_id);
                            if (target) {
                                target.unit_price = fl.unit_price;
                            }
                        });
                        this.priceChangeAlert = err.data.message;
                        throw new Error(err.data.message);
                    }
                    // Validation / Out of stock error (422)
                    if (err.status && err.status >= 400 && err.status < 500) {
                        throw err;
                    }
                    // Network failure during confirm -> fallback to offline outbox (W4 Failure paths & W13)
                }
            }

            // W13: Save to Offline Outbox in IndexedDB
            const regCode = this.currentShift.register?.code || 'KSR';
            const offlineInvoiceNo = await this.nextLocalInvoiceNo(this.currentShift.register_id, regCode);

            const offlineSaleRecord = {
                ...payload,
                invoice_no: offlineInvoiceNo,
                register_id: this.currentShift.register_id,
                register: this.currentShift.register,
                cashier: this.user,
                subtotal: totals.subtotal,
                discount_total: totals.discount_total,
                tax_total: totals.tax_total,
                grand_total: totals.grand_total,
                items: totals.lines,
                status: 'pending',
                is_offline: true,
                error_reason: null,
            };

            await db.outbox.put(offlineSaleRecord);
            this.outboxItems = await db.outbox.toArray();

            // Deduct local cached stock
            for (const line of totals.lines) {
                const prod = this.products.find((p) => p.id === line.product_id);
                if (prod && prod.track_stock) {
                    prod.stock_qty = Number((Number(prod.stock_qty) - Number(line.qty)).toFixed(3));
                    await db.products.put({ ...prod });
                }
            }

            this.activeReceipt = {
                ...offlineSaleRecord,
                is_copy: false,
            };
            this.clearCart();
            return offlineSaleRecord;
        },

        // W13.3 - W13.6: Sync Offline Outbox in batch
        async syncOutbox() {
            if (!this.isOnline || this.isSyncing || !this.user) return;
            const pending = await db.outbox.where('status').equals('pending').sortBy('created_at');
            if (!pending.length) return;

            this.isSyncing = true;
            try {
                const response = await apiRequest('/sales/batch', {
                    method: 'POST',
                    body: { sales: pending },
                });

                for (const res of response.results || []) {
                    if (res.status === 'created' || res.status === 'duplicate') {
                        await db.outbox.delete(res.client_uuid);
                    } else if (res.status === 'rejected') {
                        // W13.4: Rejected sales stay in outbox flagged needs_attention; never silently dropped
                        const item = await db.outbox.get(res.client_uuid);
                        if (item) {
                            item.status = 'needs_attention';
                            item.error_reason = res.reason || 'Ditolak oleh server';
                            await db.outbox.put(item);
                        }
                    }
                }
                this.outboxItems = await db.outbox.toArray();
                await this.syncCatalog();
            } catch (err) {
                if (err.status === 401) {
                    // W13.6: Session expired pauses sync, never drops outbox
                    this.sessionExpired = true;
                }
            } finally {
                this.isSyncing = false;
            }
        },

        async retryRejectedOutboxItem(clientUuid) {
            const item = await db.outbox.get(clientUuid);
            if (!item) return;
            item.status = 'pending';
            item.error_reason = null;
            await db.outbox.put(item);
            this.outboxItems = await db.outbox.toArray();
            await this.syncOutbox();
        },

        async removeResolvedOutboxItem(clientUuid) {
            await db.outbox.delete(clientUuid);
            this.outboxItems = await db.outbox.toArray();
        },
    },
});

