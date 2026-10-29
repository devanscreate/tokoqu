import Dexie from 'dexie';

export const db = new Dexie('TokoquPosDB');

db.version(1).stores({
    products: 'id, sku, barcode, category_id, name, is_active',
    categories: 'id, sort_order, name',
    registers: 'id, code',
    settings: 'key',
    held_carts: 'id, register_id, created_at',
    outbox: 'client_uuid, status, shift_id, created_at',
    invoice_counters: 'key', // { key: `${register_id}_${yymmdd}`, seq: number }
});

