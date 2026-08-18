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

function contractika_golden_action_surface(array $controllers): array {
    $surface = [];
    foreach($controllers as $controller) {
        $parts = explode('_', $controller, 2);
        $path = 'packages/' . $parts[0] . '/actions/' . str_replace('_', '/', str_replace('-', '-', $parts[1])) . '.php';
        $path = str_replace(['contractika/actions/'], ['contractika/actions/'], $path);
        $relative = str_replace('packages/contractika/actions/', '', $path);
        if(strpos($relative, '/') === false) {
            $relative = str_replace('_', '/', $parts[1]) . '.php';
            $path = 'packages/contractika/actions/' . $relative;
        }

        $content = is_file($path) ? file_get_contents($path) : '';
        preg_match("/'description'\s*=>\s*(\"[^\"]+\"|'[^']+')/s", $content, $description);
        preg_match("/'visibility'\s*=>\s*'([^']+)'/", $content, $visibility);
        preg_match("/'providers'\s*=>\s*\[([^\]]*)\]/s", $content, $providers);
        preg_match("/'params'\s*=>\s*\[([^\]]*)\]/s", $content, $params);

        $surface[$controller] = [
            'file_exists'     => is_file($path),
            'visibility'      => $visibility[1] ?? null,
            'has_description' => isset($description[1]),
            'has_providers'   => isset($providers[1]),
            'has_params'      => isset($params[1])
        ];
    }
    ksort($surface);
    return $surface;
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
