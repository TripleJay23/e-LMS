<?php
$json = file_get_contents(__DIR__ . '/scripts/modules_corrected.json');
$modules = json_decode($json, true);

$data = [
   'shared' => [],
   'bit_only' => [],
   'bcs_only' => []
];

foreach ($modules as $m) {
   // Map semester_roman to "Semester {roman}"
   $m['semester'] = "Semester " . $m['semester_roman'];

   $progs = $m['programs'];
   if (in_array('BIT', $progs) && in_array('BCS', $progs)) {
      $data['shared'][] = $m;
   } elseif (in_array('BIT', $progs)) {
      $data['bit_only'][] = $m;
   } elseif (in_array('BCS', $progs)) {
      $data['bcs_only'][] = $m;
   }
}

file_put_contents(__DIR__ . '/modules_categorized.json', json_encode($data, JSON_PRETTY_PRINT));
echo "Successfully recreated modules_categorized.json\n";
echo "Shared: " . count($data['shared']) . "\n";
echo "BIT-only: " . count($data['bit_only']) . "\n";
echo "BCS-only: " . count($data['bcs_only']) . "\n";
