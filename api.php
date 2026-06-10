<?php
 $SHEET_ID = '1_GMXuv-C9Qm29awZim58uXAlFH-384WyWHyyVFQeblw';
 $cacheDir = 'cache/';
 $cacheTime = 600; // كاخ النتائج لمدة 10 دقائق

 $searchType = isset($_GET['type']) ? $_GET['type'] : '';
 $searchVal = isset($_GET['val']) ? trim($_GET['val']) : '';

if (!$searchType || !$searchVal) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

 $cacheFile = $cacheDir . md5($searchType . '_' . $searchVal) . '.json';

// 1. محاولة قراءة من الكاش
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    header('Content-Type: application/json');
    readfile($cacheFile);
    exit;
}

// 2. تحديد العمود للبحث
 $colIndex = 0;
if ($searchType === 'nationalId') $colIndex = 0;
elseif ($searchType === 'name') $colIndex = 1;
elseif ($searchType === 'address') $colIndex = 2;
elseif ($searchType === 'subNumber') $colIndex = 4;
elseif ($searchType === 'barcode') $colIndex = 5;
elseif ($searchType === 'phone') $colIndex = 12;

// 3. بناء الاستعلام
 $colLetter = getColLetter($colIndex);
 $tq = urlencode("select * where " . $colLetter . " contains '" . addslashes($searchVal) . "'");
 $url = "https://docs.google.com/spreadsheets/d/{$SHEET_ID}/gviz/tq?tqx=out:json&tq={$tq}";

// 4. التنفيذ
 $ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
 $data = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['error' => 'Connection Error: ' . curl_error($ch)]);
    exit;
}
curl_close($ch);

 $results = [];
if ($data) {
    preg_match('/google\.visualization\.Query\.setResponse\(([\s\S]*)\);?\s*$/', $data, $matches);
    if (!empty($matches[1])) {
        $json = json_decode($matches[1], true);
        if (isset($json['table']['rows'])) {
            $rows = $json['table']['rows'];
            $cols = $json['table']['cols'];
            
            $colNames = [];
            foreach ($cols as $c) {
                $colNames[] = isset($c['label']) ? trim($c['label']) : trim($c['id']);
            }

            foreach ($rows as $row) {
                $rowData = [];
                foreach ($row['c'] as $index => $cell) {
                    if (isset($colNames[$index])) {
                        $val = isset($cell['f']) ? $cell['f'] : (isset($cell['v']) ? $cell['v'] : '');
                        $rowData[$colNames[$index]] = (string)$val;
                    }
                }
                if (!empty(array_filter($rowData))) {
                    $results[] = $rowData;
                }
            }
            
            // حفظ النتيجة في الكاش
            file_put_contents($cacheFile, json_encode($results));
        }
    }
}

header('Content-Type: application/json');
echo json_encode($results);

// دالة تحويل رقم العمود إلى حرف (مُحسّنة ومضمونة)
function getColLetter($index) {
    $letters = '';
    while ($index >= 0) {
        $letters = chr($index % 26 + ord('A')) . $letters;
        $index = floor($index / 26) - 1;
    }
    return $letters;
}
?>