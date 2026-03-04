<?php
require_once 'includes/randomizer_catalog.php';
$cat = randomizer_catalog();
$prog = randomizer_programs();

echo '=== CATALOG GROUPS ===' . PHP_EOL;
foreach ($cat as $group => $exercises) {
    echo $group . ': ' . count($exercises) . ' exercises' . PHP_EOL;
}

echo PHP_EOL . '=== PROGRAMS ===' . PHP_EOL;
$allMissing = [];
foreach ($prog as $key => $sheets) {
    echo '[' . $key . ']' . PHP_EOL;
    foreach ($sheets as $label => $groups) {
        echo '  Ficha: ' . $label . PHP_EOL;
        foreach ($groups as $g) {
            $count = count($cat[$g] ?? []);
            $status = $count > 0 ? 'OK (' . $count . ')' : '!!! MISSING !!!';
            if ($count === 0)
                $allMissing[] = $g;
            echo '    - ' . $g . ': ' . $status . PHP_EOL;
        }
    }
}

// Sample pick test
echo PHP_EOL . '=== SAMPLE PICKS (simulating randomizer) ===' . PHP_EOL;
foreach (['Peitoral', 'Costas', 'Biceps', 'Triceps', 'Ombro', 'Quadriceps', 'Gluteo', 'Posterior', 'Mobilidade', 'Cardio'] as $g) {
    $list = $cat[$g] ?? [];
    if ($list) {
        $pick = $list[array_rand($list)];
        echo $g . ': "' . $pick[0] . '" => ' . ($pick[1] ? substr($pick[1], 0, 60) : 'no url') . PHP_EOL;
    }
}

// JSON encode test (to catch any encoding issues that would break JS)
echo PHP_EOL . '=== JSON ENCODE TEST ===' . PHP_EOL;
$json = json_encode($cat, JSON_UNESCAPED_UNICODE);
$jsonProg = json_encode($prog, JSON_UNESCAPED_UNICODE);
echo 'Catalog JSON length: ' . strlen($json) . ' bytes' . PHP_EOL;
echo 'Programs JSON length: ' . strlen($jsonProg) . ' bytes' . PHP_EOL;
echo 'JSON error: ' . (json_last_error() === JSON_ERROR_NONE ? 'NONE' : json_last_error_msg()) . PHP_EOL;

if (empty($allMissing)) {
    echo PHP_EOL . 'ALL GOOD! No missing groups.' . PHP_EOL;
} else {
    echo PHP_EOL . 'MISSING GROUPS: ' . implode(', ', array_unique($allMissing)) . PHP_EOL;
}
