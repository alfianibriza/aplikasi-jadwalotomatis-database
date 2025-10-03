<?php
// Mengatur header untuk output JSON
header('Content-Type: application/json');
// Menyertakan file koneksi database
require_once 'db_connect.php';

// Mendapatkan parameter 'action' dari request
$action = isset($_GET['action']) ? $_GET['action'] : '';
$input = json_decode(file_get_contents('php://input'), true);

// Fungsi bantuan untuk mengirim response JSON
function json_response($data, $success = true) {
    echo json_encode(['success' => $success, 'data' => $data]);
    exit();
}

// Fungsi bantuan untuk menjalankan query
function execute_query($conn, $sql, $params = null) {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

// Memproses request berdasarkan nilai 'action'
switch ($action) {
    // Mengambil semua data master
    case 'get_all_data':
        $data = [];
        $result_kelas = $conn->query("SELECT id, nama FROM kelas ORDER BY nama ASC");
        $data['kelas'] = $result_kelas->fetch_all(MYSQLI_ASSOC);

        $result_guru = $conn->query("SELECT id, nama FROM guru ORDER BY nama ASC");
        $data['guru'] = $result_guru->fetch_all(MYSQLI_ASSOC);

        $result_mapel = $conn->query("SELECT id, nama FROM mata_pelajaran ORDER BY nama ASC");
        $data['mata_pelajaran'] = $result_mapel->fetch_all(MYSQLI_ASSOC);

        $result_alokasi = $conn->query("
            SELECT a.id, a.id_mapel, a.id_guru, a.id_kelas, a.sesi, 
                   m.nama AS nama_mapel, g.nama AS nama_guru, k.nama AS nama_kelas
            FROM alokasi a
            JOIN mata_pelajaran m ON a.id_mapel = m.id
            JOIN guru g ON a.id_guru = g.id
            JOIN kelas k ON a.id_kelas = k.id
        ");
        $data['alokasi'] = $result_alokasi->fetch_all(MYSQLI_ASSOC);
        
        $result_jadwal = $conn->query("SELECT id_kelas, data_jadwal FROM jadwal");
        $schedules = [];
        while ($row = $result_jadwal->fetch_assoc()) {
            $kelas_nama = $conn->query("SELECT nama FROM kelas WHERE id = " . $row['id_kelas'])->fetch_assoc()['nama'];
            $schedules[$kelas_nama] = json_decode($row['data_jadwal'], true);
        }
        $data['schedules'] = $schedules;

        json_response($data);
        break;

    // Menambah dan mengubah data (CRUD)
    case 'save_item':
        $table = $input['table'];
        $id = $input['id'];
        $data = $input['data'];
        
        if ($id) { // Update
            $fields = [];
            $params = [];
            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $id;
            $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE id = ?";
            execute_query($conn, $sql, $params);
            json_response(['id' => $id]);
        } else { // Insert
            $keys = array_keys($data);
            $values = array_values($data);
            $sql = "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES (" . rtrim(str_repeat('?,', count($values)), ',') . ")";
            $stmt = execute_query($conn, $sql, $values);
            json_response(['id' => $stmt->insert_id]);
        }
        break;
        
    // Menghapus data
    case 'delete_item':
        $table = $input['table'];
        $id = $input['id'];
        execute_query($conn, "DELETE FROM $table WHERE id = ?", [$id]);
        json_response(['id' => $id]);
        break;
        
    // Menyimpan semua jadwal
    case 'save_schedules':
        $schedules = $input['schedules'];
        $conn->query("DELETE FROM jadwal"); // Hapus jadwal lama
        foreach ($schedules as $nama_kelas => $data_jadwal) {
            // Dapatkan ID kelas berdasarkan nama
            $stmt_kelas = execute_query($conn, "SELECT id FROM kelas WHERE nama = ?", [$nama_kelas]);
            $result_kelas = $stmt_kelas->get_result();
            if ($result_kelas->num_rows > 0) {
                $id_kelas = $result_kelas->fetch_assoc()['id'];
                $json_jadwal = json_encode($data_jadwal);
                execute_query($conn, "INSERT INTO jadwal (id_kelas, data_jadwal) VALUES (?, ?)", [$id_kelas, $json_jadwal]);
            }
        }
        json_response('Jadwal berhasil disimpan');
        break;

    // Menghapus semua data
    case 'delete_all_data':
        // TRUNCATE akan mereset auto-increment
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("TRUNCATE TABLE alokasi");
        $conn->query("TRUNCATE TABLE jadwal");
        $conn->query("TRUNCATE TABLE kelas");
        $conn->query("TRUNCATE TABLE guru");
        $conn->query("TRUNCATE TABLE mata_pelajaran");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        json_response('Semua data berhasil dihapus');
        break;

    default:
        json_response('Action tidak valid', false);
        break;
}

$conn->close();
?>
