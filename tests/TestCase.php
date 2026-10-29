<?php

namespace Tests;

use AllowDynamicProperties;
use App\Models\Product;
use App\Models\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

#[AllowDynamicProperties]
abstract class TestCase extends BaseTestCase
{
    public User $manager;

    public User $cashier;

    public Register $register;

    public Product $product;
}

