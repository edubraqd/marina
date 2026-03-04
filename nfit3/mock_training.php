<?php
$dataDir = __DIR__ . '/data/users_training';
if (!is_dir($dataDir))
    mkdir($dataDir, 0777, true);

$dummyData = [
    'title' => 'Treino Fictício UX',
    'instructions' => 'Teste das animações Pro Max',
    'exercises' => [
        [
            'name' => 'Supino Inclinado (Halteres)',
            'cues' => '{"series":"4","reps":"10-12","load":"","notes":"Contraia o peitoral","order":"1"}',
            'sheet_idx' => 'sheet1',
            'sheet_title' => 'Ficha A - Peito',
            'video_url' => 'https://youtube.com'
        ],
        [
            'name' => 'Crucifixo Máquina',
            'cues' => '{"series":"3","reps":"15","load":"","notes":"Foco no alongamento","order":"2"}',
            'sheet_idx' => 'sheet1',
            'sheet_title' => 'Ficha A - Peito',
            'video_url' => ''
        ],
        [
            'name' => 'Tríceps Corda',
            'cues' => '{"series":"4","reps":"falha","load":"","notes":"","order":"3"}',
            'sheet_idx' => 'sheet1',
            'sheet_title' => 'Ficha A - Peito',
            'video_url' => ''
        ]
    ],
    'updated_at' => date('Y-m-d H:i:s')
];

file_put_contents($dataDir . '/teste@exemplo.com.json', json_encode($dummyData, JSON_PRETTY_PRINT));
echo "Mock training data created safely.";
