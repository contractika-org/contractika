<?php
use core\setting\Setting;

Setting::assert_value('core', 'security', 'passkey_creation', '0');
Setting::assert_value('core', 'security', 'auth.account_creation.enabled', false);
Setting::assert_value('core', 'security', 'auth.passkey.enabled', '0');

Setting::assert_value('core', 'locale', 'currency.code', 'EUR');
Setting::assert_value('core', 'locale', 'date.format', 'd/m/Y');
Setting::assert_value('core', 'locale', 'time.format', 'H:i');
Setting::assert_value('core', 'locale', 'number.thousands_separator', '.');
Setting::assert_value('core', 'locale', 'number.decimal_separator', ',');
Setting::assert_value('core', 'locale', 'number.decimal_precision', 2);
Setting::assert_value('core', 'locale', 'currency.symbol', '€');
Setting::assert_value('core', 'locale', 'unit.length', 'm');
Setting::assert_value('core', 'locale', 'unit.weight', 'kg');
Setting::assert_value('core', 'locale', 'unit.volume', 'm3');
Setting::assert_value('core', 'locale', 'unit.surface', 'm2');
Setting::assert_value('core', 'locale', 'time_zone', 'Europe/Brussels');
Setting::assert_value('core', 'locale', 'paper.format', 'A4');

Setting::assert_value('core', 'organization', 'company.id', 1);