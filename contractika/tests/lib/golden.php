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

function contractika_golden_task_seed(): array {
    $path = 'packages/contractika/init/data/core_Task.json';
    $json = json_decode(file_get_contents($path), true);
    $tasks = [];
    foreach($json[0]['data'] ?? [] as $task) {
        $tasks[] = [
            'name'         => $task['name'],
            'controller'   => $task['controller'],
            'is_active'    => $task['is_active'],
            'is_recurring' => $task['is_recurring'],
            'repeat_axis'  => $task['repeat_axis'],
            'repeat_step'  => $task['repeat_step'],
            'time_utc'     => substr($task['moment'], 11, 5)
        ];
    }
    usort($tasks, fn($a, $b) => strcmp($a['controller'], $b['controller']));
    return $tasks;
}

function contractika_golden_controller_path(string $type, string $controller): string {
    if(strpos($controller, '_') === false) {
        return '';
    }

    [$package, $operation] = explode('_', $controller, 2);
    $folder = ($type === 'get') ? 'data' : 'actions';

    return 'packages/' . $package . '/' . $folder . '/' . str_replace('_', '/', $operation) . '.php';
}

function contractika_golden_controller_surface(string $type, string $controller): array {
    $path = contractika_golden_controller_path($type, $controller);
    $content = is_file($path) ? file_get_contents($path) : '';
    $params_block = contractika_golden_array_block($content, 'params');
    $providers_block = contractika_golden_array_block($content, 'providers');

    preg_match("/'description'\s*=>\s*(\"[^\"]+\"|'[^']+')/s", $content, $description);
    preg_match("/'visibility'\s*=>\s*'([^']+)'/", $content, $visibility);
    preg_match_all("/(?:\\\\?eQual)::run\s*\(\s*'([^']+)'\s*,\s*'([^']+)'/", $content, $calls, PREG_SET_ORDER);

    return [
        'type'            => $type,
        'controller'      => $controller,
        'path'            => $path,
        'file_exists'     => is_file($path),
        'has_announce'    => (bool) preg_match('/(?:eQual::)?announce\s*\(\s*\[/', $content),
        'has_description' => isset($description[1]),
        'visibility'      => $visibility[1] ?? 'protected',
        'providers'       => contractika_golden_array_string_values($providers_block ?? ''),
        'params'          => contractika_golden_array_top_level_keys($params_block ?? ''),
        'calls'           => array_map(
            fn($call) => [
                'type'       => $call[1],
                'controller' => $call[2],
                'path'       => contractika_golden_controller_path($call[1], $call[2]),
                'resolves'   => (strpos($call[2], 'contractika_') !== 0) || is_file(contractika_golden_controller_path($call[1], $call[2]))
            ],
            $calls
        )
    ];
}

function contractika_golden_operation_graph(array $entrypoints): array {
    $queue = $entrypoints;
    $surface = [];

    while(count($queue)) {
        [$type, $controller] = array_shift($queue);
        $key = $type . ':' . $controller;
        if(isset($surface[$key])) {
            continue;
        }

        $surface[$key] = contractika_golden_controller_surface($type, $controller);

        foreach($surface[$key]['calls'] as $call) {
            if(strpos($call['controller'], 'contractika_') !== 0) {
                continue;
            }
            if(!in_array($call['type'], ['get', 'do', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }
            $queue[] = [$call['type'], $call['controller']];
        }
    }

    ksort($surface);
    return $surface;
}

function contractika_golden_array_block(string $content, string $key): ?string {
    if(!preg_match("/['\"]" . preg_quote($key, '/') . "['\"]\s*=>\s*\[/", $content, $match, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $start = strpos($content, '[', $match[0][1]);
    if($start === false) {
        return null;
    }

    return contractika_golden_bracket_block($content, $start);
}

function contractika_golden_bracket_block(string $content, int $start): ?string {
    $depth = 0;
    $quote = null;
    $escape = false;
    $length = strlen($content);

    for($i = $start; $i < $length; ++$i) {
        $char = $content[$i];

        if($quote) {
            if($escape) {
                $escape = false;
            }
            elseif($char === '\\') {
                $escape = true;
            }
            elseif($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if($char === "'" || $char === '"') {
            $quote = $char;
            continue;
        }

        if($char === '[') {
            ++$depth;
        }
        elseif($char === ']') {
            --$depth;
            if($depth === 0) {
                return substr($content, $start, $i - $start + 1);
            }
        }
    }

    return null;
}

function contractika_golden_array_top_level_keys(string $block): array {
    $keys = [];
    $depth = 0;
    $quote = null;
    $escape = false;
    $length = strlen($block);

    for($i = 0; $i < $length; ++$i) {
        $char = $block[$i];

        if($quote) {
            if($escape) {
                $escape = false;
            }
            elseif($char === '\\') {
                $escape = true;
            }
            elseif($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if($char === "'" || $char === '"') {
            if($depth === 1) {
                [$value, $end] = contractika_golden_string_literal($block, $i);
                $cursor = $end + 1;
                while($cursor < $length && ctype_space($block[$cursor])) {
                    ++$cursor;
                }
                if(substr($block, $cursor, 2) === '=>') {
                    $keys[] = $value;
                }
                $i = $end;
                continue;
            }
            $quote = $char;
            continue;
        }

        if($char === '[') {
            ++$depth;
        }
        elseif($char === ']') {
            --$depth;
        }
    }

    sort($keys);
    return array_values(array_unique($keys));
}

function contractika_golden_array_string_values(string $block): array {
    preg_match_all("/'([^']+)'|\"([^\"]+)\"/", $block, $matches, PREG_SET_ORDER);
    $values = [];
    foreach($matches as $match) {
        $values[] = $match[1] !== '' ? $match[1] : $match[2];
    }
    sort($values);
    return array_values(array_unique($values));
}

function contractika_golden_string_literal(string $content, int $start): array {
    $quote = $content[$start];
    $value = '';
    $escape = false;
    $length = strlen($content);

    for($i = $start + 1; $i < $length; ++$i) {
        $char = $content[$i];
        if($escape) {
            $value .= $char;
            $escape = false;
            continue;
        }
        if($char === '\\') {
            $escape = true;
            continue;
        }
        if($char === $quote) {
            return [$value, $i];
        }
        $value .= $char;
    }

    return [$value, $start];
}
