<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle CSV Import
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        // Skip header row
        fgetcsv($file);
        $imported = 0;
        while (($row = fgetcsv($file)) !== false) {
            $name = isset($row[0]) ? trim($row[0]) : '';
            $email = isset($row[1]) ? trim($row[1]) : '';
            $phone = isset($row[2]) ? trim($row[2]) : '';
            $address = isset($row[3]) ? trim($row[3]) : '';
            $group_id = isset($row[4]) && $row[4] !== '' ? $row[4] : null;

            if ($name != '') {
                $stmt = $pdo->prepare("INSERT INTO contacts (user_id, group_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $group_id, $name, $email, $phone, $address]);
                $imported++;
            }
        }
        fclose($file);
        $message = "$imported contacts imported successfully!";
    } else {
        $message = "Error uploading CSV file.";
    }
}

// Handle contact operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['import_csv'])) {
    if (isset($_POST['add_contact'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $group_id = $_POST['group_id'] ?: NULL;
        
        $stmt = $pdo->prepare("INSERT INTO contacts (user_id, group_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $group_id, $name, $email, $phone, $address])) {
            $message = "Contact added successfully!";
        }
    }
    
    if (isset($_POST['update_contact'])) {
        $contact_id = $_POST['contact_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $group_id = $_POST['group_id'] ?: NULL;
        
        $stmt = $pdo->prepare("UPDATE contacts SET name=?, email=?, phone=?, address=?, group_id=? WHERE id=? AND user_id=?");
        if ($stmt->execute([$name, $email, $phone, $address, $group_id, $contact_id, $user_id])) {
            $message = "Contact updated successfully!";
        }
    }
    
    if (isset($_POST['add_group'])) {
        $group_name = trim($_POST['group_name']);
        $stmt = $pdo->prepare("INSERT INTO contact_groups (user_id, group_name) VALUES (?, ?)");
        if ($stmt->execute([$user_id, $group_name])) {
            $message = "Group added successfully!";
        }
    }
}

// Handle delete operations
if (isset($_GET['delete'])) {
    $contact_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM contacts WHERE id=? AND user_id=?");
    if ($stmt->execute([$contact_id, $user_id])) {
        $message = "Contact deleted successfully!";
    }
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$group_filter = isset($_GET['group_filter']) ? $_GET['group_filter'] : '';

// Get contacts with search and filter
$sql = "SELECT c.*, g.group_name FROM contacts c 
        LEFT JOIN contact_groups g ON c.group_id = g.id 
        WHERE c.user_id = ?";
$params = [$user_id];

if ($search) {
    $sql .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($group_filter) {
    $sql .= " AND c.group_id = ?";
    $params[] = $group_filter;
}

$sql .= " ORDER BY c.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contacts = $stmt->fetchAll();

// Get groups for dropdown
$stmt = $pdo->prepare("SELECT * FROM contact_groups WHERE user_id = ? ORDER BY group_name");
$stmt->execute([$user_id]);
$groups = $stmt->fetchAll();

// Get contact for editing
$edit_contact = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id=? AND user_id=?");
    $stmt->execute([$edit_id, $user_id]);
    $edit_contact = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Contact Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-address-book"></i> Contact Manager
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a class="btn btn-outline-light btn-sm" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add/Edit Contact Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-plus"></i> <?php echo $edit_contact ? 'Edit Contact' : 'Add New Contact'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($edit_contact): ?>
                                <input type="hidden" name="contact_id" value="<?php echo $edit_contact['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo $edit_contact ? htmlspecialchars($edit_contact['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo $edit_contact ? htmlspecialchars($edit_contact['email']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo $edit_contact ? htmlspecialchars($edit_contact['phone']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo $edit_contact ? htmlspecialchars($edit_contact['address']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="group_id" class="form-label">Group</label>
                                <select class="form-select" id="group_id" name="group_id">
                                    <option value="">No Group</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>" 
                                                <?php echo ($edit_contact && $edit_contact['group_id'] == $group['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($group['group_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="<?php echo $edit_contact ? 'update_contact' : 'add_contact'; ?>" 
                                    class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> <?php echo $edit_contact ? 'Update Contact' : 'Add Contact'; ?>
                            </button>
                            
                            <?php if ($edit_contact): ?>
                                <a href="dashboard.php" class="btn btn-secondary w-100 mt-2">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Add Group Form -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="fas fa-layer-group"></i> Add New Group</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="input-group">
                                <input type="text" class="form-control" name="group_name" placeholder="Group name" required>
                                <button type="submit" name="add_group" class="btn btn-outline-primary">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Contacts List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Your Contacts</h5>
                    </div>
                    <div class="card-body">
                        <!-- Import/Export CSV -->
                        <form action="dashboard.php" method="post" enctype="multipart/form-data" style="display:inline-block; margin-right:10px;">
                            <input type="file" name="csv_file" accept=".csv" required>
                            <button type="submit" name="import_csv" class="btn btn-success btn-sm">Import CSV</button>
                        </form>
                        <a href="export_csv.php" class="btn btn-info btn-sm">Export CSV</a>

                        <!-- Search and Filter -->
                        <form method="GET" class="row mb-3 mt-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search contacts..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="group_filter">
                                    <option value="">All Groups</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>" 
                                                <?php echo ($group_filter == $group['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($group['group_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>

                        <!-- Contacts Table -->
                        <div class="table-responsive">
                            <table id="contactsTable" class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Group</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['phone']); ?></td>
                                            <td>
                                                <?php if ($contact['group_name']): ?>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($contact['group_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">No Group</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?edit=<?php echo $contact['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $contact['id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this contact?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                        data-bs-target="#viewModal<?php echo $contact['id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- View Contact Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $contact['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Contact Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($contact['name']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($contact['email']); ?></p>
                                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($contact['phone']); ?></p>
                                                        <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($contact['address'])); ?></p>
                                                        <p><strong>Group:</strong> <?php echo $contact['group_name'] ? htmlspecialchars($contact['group_name']) : 'No Group'; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#contactsTable').DataTable({
                "pageLength": 10,
                "responsive": true
            });
        });
    </script>
</body>
</html>

