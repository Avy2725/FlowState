<?php
session_start();
require_once "connection.php";

if (!isset($_SESSION['userType']) || strtolower($_SESSION['userType']) !== 'admin') {
    header('Location: login.php');
    exit();
}

// Array of foul words to search for - Add or remove words as needed
$foulWords = array('fuck', 'sex', 'faggot', 'cunt', 'inappropriate');

$feedback = '';
$viewingUserTasks = false;
$viewingUser = null;
$userTasks = null;
$userProjects = null;
$searchingFoulWords = false;
$foulWordResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['userId'], $_POST['action'])) {
    $userId = intval($_POST['userId']);
    $action = $_POST['action'];

    if ($userId > 0 && in_array($action, ['deactivate', 'activate'], true)) {
        $newStatus = $action === 'deactivate' ? 'Inactive' : 'Active';
        $updateSql = "UPDATE ca_users SET ca_status = '" . $newStatus . "' WHERE ca_Id = '" . $userId . "'";
        
        // If the status update was successful, log the action
        if ($conn->query($updateSql)) {
            $feedback = "Account #$userId has been set to $newStatus.";
            
            // Get the Admin's ID from the session (set during login)
            $adminId = isset($_SESSION['student_number']) ? intval($_SESSION['student_number']) : 0;
            
            // Create a clear action message
            if ($newStatus === 'Inactive') {
                $actionText = "Suspended/Deactivated User ID: $userId";
            } else {
                $actionText = "Reactivated User ID: $userId";
            }
            
            // Insert the action into the ca_logs table
            $logSql = "INSERT INTO ca_logs (ca_id, ca_action, ca_datetime) VALUES ('" . $adminId . "', '" . $conn->real_escape_string($actionText) . "', NOW())";
            $conn->query($logSql);
        }
    }
}

if (!empty($_GET['viewUserTasks'])) {
    $viewingUserTasks = true;
    $taskUserId = intval($_GET['viewUserTasks']);
    
    $userSql = "SELECT * FROM ca_users WHERE ca_Id = '" . $taskUserId . "'";
    $userResult = $conn->query($userSql);
    $viewingUser = $userResult->fetch_assoc();
    
    $tasksSql = "SELECT * FROM todo_tasks WHERE user_id = '" . $taskUserId . "' ORDER BY task_due_date ASC";
    $userTasks = $conn->query($tasksSql);
    
    $projectsSql = "SELECT * FROM todo_projects WHERE user_id = '" . $taskUserId . "' ORDER BY project_id DESC";
    $userProjects = $conn->query($projectsSql);
}


$search = '';
$whereSql = '';

if (!empty($_GET['viewUserTasks'])) {
    $viewingUserTasks = true;
    $taskUserId = intval($_GET['viewUserTasks']);
    
    // Fetch user details
    $userSql = "SELECT * FROM ca_users WHERE ca_Id = '" . $taskUserId . "'";
    $userResult = $conn->query($userSql);
    $viewingUser = $userResult->fetch_assoc();
    
    // Log the viewing action
    if ($viewingUser) {
        $adminId = isset($_SESSION['student_number']) ? intval($_SESSION['student_number']) : 0;
        $actionText = "Viewed Tasks of User ID: " . $taskUserId;
        $logSql = "INSERT INTO ca_logs (ca_id, ca_action, ca_datetime) VALUES ('" . $adminId . "', '" . $conn->real_escape_string($actionText) . "', NOW())";
        $conn->query($logSql);
    }
    
    $tasksSql = "SELECT * FROM todo_tasks WHERE user_id = '" . $taskUserId . "' ORDER BY task_due_date ASC";
    $userTasks = $conn->query($tasksSql);
    
    $projectsSql = "SELECT * FROM todo_projects WHERE user_id = '" . $taskUserId . "' ORDER BY project_id DESC";
    $userProjects = $conn->query($projectsSql);
}

if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $whereSql = "WHERE ca_lname LIKE '%" . $search . "%' OR ca_fname LIKE '%" . $search . "%' OR ca_userName LIKE '%" . $search . "%' OR ca_email LIKE '%" . $search . "%'";
}

$usersSql = "SELECT * FROM ca_users " . $whereSql . " ORDER BY ca_Id DESC";
$usersResult = $conn->query($usersSql);

$logsSql = "SELECT * FROM ca_logs ORDER BY ca_datetime DESC";
$logsResult = $conn->query($logsSql);

// Search for foul words in tasks
if (!empty($_GET['searchFoulWords'])) {
    $searchingFoulWords = true;
    $foulWordResults = array();
    
    foreach ($foulWords as $word) {
        $wordSql = "SELECT t.*, u.ca_fname, u.ca_lname, u.ca_status FROM todo_tasks t LEFT JOIN ca_users u ON t.user_id = u.ca_Id WHERE (t.task_title LIKE '%" . $word . "%' OR t.task_description LIKE '%" . $word . "%') ORDER BY t.task_due_date ASC";
        $wordResult = $conn->query($wordSql);
        
        if ($wordResult && $wordResult->num_rows > 0) {
            while ($task = $wordResult->fetch_assoc()) {
                $task['matched_word'] = $word;
                $foulWordResults[] = $task;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/custom.css">
    <link rel="stylesheet" href="styles/admin-dashboard.css">
</head>
<body>
<div class="admin-dashboard-shell">
<nav class="navbar navbar-expand-lg admin-navbar mb-4">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="#">
            <span class="brand-mark"></span>
            <span>FlowState Admin</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNav">
            <div class="ms-auto d-flex align-items-center gap-3 flex-column flex-sm-row">
                <div class="dropdown">
                    <a class="btn btn-light profile-pill d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo $_SESSION['ca_ImgPath']; ?>" alt="Admin avatar">
                        <span><?php echo $_SESSION['full']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="card dashboard-header shadow-sm p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start align-items-md-center">
            <div>
                <h2 class="mb-1">Admin Management</h2>
                <p class="text-muted mb-0">Manage users and view system logs.</p>
            </div>
            <a href="?searchFoulWords=1" class="btn btn-warning">Scan for Foul Words</a>
        </div>
    </div>

    <?php if ($feedback): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo $feedback; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($viewingUserTasks && $viewingUser): ?>
    <div class="card dashboard-table-card shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3><?php echo $viewingUser['ca_fname'] . ' ' . $viewingUser['ca_lname']; ?>'s Tasks & Projects</h3>
                    <p class="text-muted mb-0"><?php echo $viewingUser['ca_email']; ?> - <?php echo $viewingUser['ca_userType']; ?></p>
                </div>
                <a href="admindashboard.php" class="btn btn-secondary">Back to Users</a>
            </div>

            <div class="mb-5">
                <h5 class="mb-3">Projects</h5>
                <?php if ($userProjects && $userProjects->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project Name</th>
                                    <th>Description</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($project = $userProjects->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo $project['project_name']; ?></strong></td>
                                        <td><?php echo isset($project['project_description']) ? $project['project_description'] : '-'; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($project['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No projects found.</p>
                <?php endif; ?>
            </div>

            <div>
                <h5 class="mb-3">Tasks</h5>
                <?php if ($userTasks && $userTasks->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Task Title</th>
                                    <th>Description</th>
                                    <th>Due Date</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($task = $userTasks->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo $task['task_title']; ?></strong></td>
                                        <td><?php echo isset($task['task_description']) ? $task['task_description'] : '-'; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($task['task_due_date'])); ?></td>
                                        <td><span class="badge bg-warning"><?php echo $task['task_priority']; ?></span></td>
                                        <td><span class="badge bg-info"><?php echo $task['task_status']; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No tasks found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php elseif ($searchingFoulWords): ?>

    <div class="card dashboard-table-card shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3>Foul Words Scan Results</h3>
                    <p class="text-muted mb-0">Tasks containing flagged words</p>
                </div>
                <a href="admindashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <?php if (count($foulWordResults) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Task Title</th>
                                <th>Description</th>
                                <th>User</th>
                                <th>Flagged Word</th>
                                <th>Task Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($foulWordResults as $task): ?>
                                <tr class="table-danger">
                                    <td><strong><?php echo $task['task_title']; ?></strong></td>
                                    <td><?php echo isset($task['task_description']) ? substr($task['task_description'], 0, 50) . '...' : '-'; ?></td>
                                    <td><?php echo isset($task['ca_fname']) ? $task['ca_fname'] . ' ' . $task['ca_lname'] : 'Unknown'; ?></td>
                                    <td><span class="badge bg-danger"><?php echo $task['matched_word']; ?></span></td>
                                    <td><span class="badge bg-info"><?php echo $task['task_status']; ?></span></td>
                                    <td class="text-end">
                                        <?php if (isset($task['user_id'])): ?>
                                            <form method="post" action="admindashboard.php?searchFoulWords=1" class="d-inline">
                                                <input type="hidden" name="userId" value="<?php echo $task['user_id']; ?>">
                                                <input type="hidden" name="action" value="<?php echo (isset($task['ca_status']) && $task['ca_status'] === 'Active') ? 'deactivate' : 'activate'; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo (isset($task['ca_status']) && $task['ca_status'] === 'Active') ? 'btn-danger' : 'btn-success'; ?>" onclick="return confirm('Change status for this user?');">
                                                    <?php echo (isset($task['ca_status']) && $task['ca_status'] === 'Active') ? 'Suspend Account' : 'Reactivate'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success" role="alert">
                    No tasks found containing flagged words. All clear!
                </div>
            <?php endif; ?>

            <div class="mt-4 p-3 bg-light rounded">
                <h6 class="mb-3">Flagged Words Array</h6>
                <p class="mb-0 text-muted">Edit the array at the top of the file to add or remove words:</p>
                <code style="display: block; margin-top: 10px; padding: 10px;">$foulWords = array('badword1', 'badword2', 'badword3', 'offensive', 'inappropriate');</code>
            </div>
        </div>
    </div>
    <?php else: ?>

    <div class="card dashboard-table-card shadow-sm">
        <div class="card-body p-0">
            <ul class="nav nav-tabs" id="adminTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-content" type="button" role="tab" aria-controls="users-content" aria-selected="true">
                        Users
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-content" type="button" role="tab" aria-controls="logs-content" aria-selected="false">
                        Logs
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="adminTabContent">
                <div class="tab-pane fade show active" id="users-content" role="tabpanel" aria-labelledby="users-tab">
                    <div class="p-4">
                        <form class="search-bar mb-4" method="get" action="admindashboard.php">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="search" name="search" class="form-control" placeholder="Search user by name, username, or email" value="<?php echo $search; ?>">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">Search</button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($usersResult && $usersResult->num_rows > 0): ?>
                                        <?php while ($user = $usersResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $user['ca_Id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <img src="<?php echo $user['ca_ImgPath'] ?: 'https://via.placeholder.com/64'; ?>" width="52" height="52" alt="Avatar">
                                                        <div>
                                                            <strong><?php echo $user['ca_fname'] . ' ' . $user['ca_lname']; ?></strong><br>
                                                            <small class="text-muted"><?php echo $user['ca_gender']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo $user['ca_email']; ?></td>
                                                <td><?php echo $user['ca_userName']; ?></td>
                                                <td><?php echo $user['ca_userType']; ?></td>
                                                <td><span class="badge status-badge <?php echo $user['ca_status']; ?>"><?php echo $user['ca_status']; ?></span></td>
                                                <td><?php echo isset($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '-'; ?></td>
                                                <td class="text-end">
                                                    <div class="d-flex gap-2 justify-content-end flex-wrap">
                                                        <?php if (strtolower($user['ca_userType']) !== 'admin' && strtolower($user['ca_userType']) !== 'administrator'): ?>
                                                            <a href="?viewUserTasks=<?php echo $user['ca_Id']; ?>" class="btn btn-sm btn-outline-info">View Tasks</a>
                                                        <?php endif; ?>
                                                        <form method="post" action="admindashboard.php" class="d-inline">
                                                            <input type="hidden" name="userId" value="<?php echo $user['ca_Id']; ?>">
                                                            <input type="hidden" name="action" value="<?php echo $user['ca_status'] === 'Active' ? 'deactivate' : 'activate'; ?>">
                                                            <button type="submit" class="btn btn-sm <?php echo $user['ca_status'] === 'Active' ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                                                <?php echo $user['ca_status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No users found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="logs-content" role="tabpanel" aria-labelledby="logs-tab">
                    <div class="p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>User ID</th>
                                        <th>Action</th>
                                        <th>Date & Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($logsResult && $logsResult->num_rows > 0): ?>
                                        <?php while ($log = $logsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $log['log_id']; ?></td>
                                                <td><?php echo $log['ca_id']; ?></td>
                                                <td><span class="badge bg-info"><?php echo $log['ca_action']; ?></span></td>
                                                <td><?php echo date('M j, Y - H:i:s', strtotime($log['ca_datetime'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No logs found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>