1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
16
17
18
19
20
21
22
23
24
25
26
27
28
29
30
31
32
33
34
35
36
37
38
39
40
41
42
43
44
45
46
47
48
49
50
51
52
53
54
55
56
57
58
59
60
61
62
63
64
65
66
67
68
69
70
71
72
73
74
75
76
77
78
79
80
81
82
83
84
85
86
87
88
89
90
91
92
93
94
95
96
97
98
99
100
101
102
103
104
105
106
107
108
109
110
111
112
113
114
115
116
117
118
119
120
121
122
123
124
125
126
127
128
129
130
131
132
133
134
135
136
137
138
139
140
141
142
143
144
<?php
ob_implicit_flush(TRUE);
 
if (empty($argv[1])) {
    die('no input filename' . PHP_EOL);
}
 
$filename = $argv[1];
if (!file_exists($filename)) {
    die('no file ' . $filename);
}
 
$startTime = microtime(true);
// database params
$server = 'localhost';
$username = 'root';
$password = 'root';
$dbName = 'test';
 
// max number rows to bulk insert
$step = 5000;
 
/**
 * 1. open dbf
 * 2. read schema
 * 3. connect to db
 * 4. drop/create table with name of dbf file
 * 5. read all records in dbf and write to table
 * 6. close dbf
 * 7. print new table neme created
 */
 
 
function wrapEscape(&$value)
{
    $value = mysql_escape_string($value);
    $value = "'{$value}'";
}
 
function renderProgress($percent)
{
    echo "\rProcess " . number_format($percent, 2) . "%";
    $sigs = 50;
    echo "\t\t[";
    for ($i = 0; $i < $sigs; $i++) {
        $progress = (100 * $i) / $sigs;
        echo ($progress > $percent) ? ' ' : '=';
    }
    echo "]\r";
}
 
function _bulkInsert($conn, array $rows, $tableName = '')
{
    $list = '';
    $fields = null;
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (null == $fields) {
                $fields = join(', ', array_keys($row));
            }
            array_walk(&$row, 'wrapEscape');
            $data = $row;
            $values = join(', ', $data);
 
            $values = '(' . $values . ')';
            $list .= ( $list ? ', ' : '') . $values;
        }
    }
    if (!empty($list)) {
        // insert tables
        $sql = "INSERT IGNORE INTO {$tableName} ($fields) VALUES {$list}";
        $res = mysql_query($sql);
        if (!$res) {
            echo mysql_error() . PHP_EOL;
        }
    }
}
 
if ('zip' === array_pop(explode('.', $filename))) {
    // unpack Zip File
    $unpackedFilePath = substr($filename, 0, strpos($filename, '.zip'));
    if (!file_exists($unpackedFilePath)) {
        $zip = new ZipArchive;
        $zip->open($filename);
        $zip->extractTo(__DIR__);
        $zip->close();
    }
    $filename = $unpackedFilePath;
}
 
$start = 1;
$_conn = dbase_open($filename, 0);
$max = dbase_numrecords($_conn);
 
$line = array();
 
$line = dbase_get_record_with_names($_conn, 1);
$schema = dbase_get_header_info($_conn);
 
$tableName = substr($filename, 0, strpos($filename, '.'));
 
 
// connect to db
$_dbConnect = mysql_connect($server, $username, $password);
$sql = 'use ' . $dbName;
$res = mysql_query($sql, $_dbConnect);
 
 
// drop table if exists
$sql = 'DROP TABLE IF EXISTS `' . $tableName . '`';
$res = mysql_query($sql, $_dbConnect);
 
$fieldList = '';
foreach ($schema as $field) {
    $fieldList .= ( $fieldList == '' ? '' : ',' . PHP_EOL) . '`' . $field['name'] . '` varchar(' . $field['length'] . ') DEFAULT NULL';
}
$sql = "
CREATE TABLE `{$tableName}` ("
        . PHP_EOL . $fieldList
        . PHP_EOL . ") ENGINE=MyISAM
";
 
$res = mysql_query($sql, $_dbConnect);
 
 
for ($start = 1; $start < $max; $start+=$step) {
 
    $i = $start;
    $insertData = array();
    $q = 0;
    while ($line = dbase_get_record_with_names($_conn, $i)) {
        if (++$q > $step OR ++$i > $max)
            break;
        foreach ($line as $key => $val) {
            $line[$key] = trim(iconv("cp866", "UTF-8", $val), ' "');
        }
        unset($line['deleted']);
        $insertData[] = $line;
    }// while
    _bulkInsert($_conn, $insertData, $tableName);
    renderProgress((100 * $start) / $max);
}// for
dbase_close($_conn);
echo "\nImported rows: {$max}. \nExecuted time: " . number_format(microtime(true) - $startTime) . " s" . PHP_EOL;
