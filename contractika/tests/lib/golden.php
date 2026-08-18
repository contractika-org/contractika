<?php
/*
    This file is part of the Contractika contract management software.
    Author: Yesbabylon SRL, 2022-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

function contractika_golden_path(string $name): string {
    return __DIR__ . '/../expected/' . $name . '.json';
}

function contractika_golden_assert(string $name, array $actual): bool {
    $actual = contractika_golden_normalize($actual);
    $path = contractika_golden_path($name);

    if(getenv('CONTRACTIKA_RECORD_GOLDEN')) {
        if(!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        return true;
    }

    if(!is_file($path)) {
        return false;
    }

    $expected = contractika_golden_normalize(json_decode(file_get_contents($path), true));
    return json_encode($expected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) === json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function contractika_golden_normalize(array $value): array {
    return contractika_golden_sort(contractika_golden_scrub($value));
}

function contractika_golden_sort($value) {
    if(!is_array($value)) {
        return $value;
    }
    foreach($value as $key => $item) {
        $value[$key] = contractika_golden_sort($item);
    }
    if(array_keys($value) !== range(0, count($value) - 1)) {
        ksort($value);
    }
    return $value;
}

function contractika_golden_scrub($value) {
    if(is_array($value)) {
        $result = [];
        foreach($value as $key => $item) {
            if(in_array($key, ['id', 'created', 'modified', 'creator', 'modifier', 'deleted'], true)) {
                continue;
            }
            $result[$key] = contractika_golden_scrub($item);
        }
        return $result;
    }

    if(is_string($value)) {
        $value = preg_replace('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', '<datetime>', $value);
        $value = preg_replace('/\b\d+\[(\d+)\]/', '<external>[<id>]', $value);
        $value = preg_replace('/\[(\d+)\]/', '[<id>]', $value);
    }

    return $value;
}
