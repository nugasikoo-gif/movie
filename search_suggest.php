<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$q = requestString($_GET, 'q', 60);
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];
$stmt = $conn->prepare(
    "SELECT id, title, poster, release_date
     FROM movies
     WHERE title LIKE ?
     ORDER BY CASE WHEN title LIKE ? THEN 0 ELSE 1 END, title ASC
     LIMIT 8"
);
if ($stmt) {
    $like = '%' . $q . '%';
    $prefix = $q . '%';
    $stmt->bind_param('ss', $like, $prefix);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $year = '';
        if (!empty($row['release_date'])) {
            $year = substr($row['release_date'], 0, 4);
        }
        $poster = $row['poster'];
        if ($poster && !preg_match('#^https?://#i', $poster)) {
            $poster = '/' . ltrim($poster, '/');
        }
        $results[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'poster' => $poster,
            'year' => $year,
            'url' => 'movie.php?id=' . (int) $row['id'],
        ];
    }
    $stmt->close();
}

echo json_encode(['results' => $results]);
