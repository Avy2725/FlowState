<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['student_number'])) {
    header('Location: login.php');
    exit();
}

$userId = intval($_SESSION['student_number']);
$feedback = '';
$error = '';

function computeDaysLeftLabel($dueDate) {
    if (empty($dueDate)) {
        return '';
    }

    $due = new DateTime($dueDate);
    $today = new DateTime('today');
    $diff = $today->diff($due);
    $days = (int) $diff->format('%r%a');

    if ($days < 0) {
        return abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' overdue';
    }

    if ($days === 0) {
        return 'Today';
    }

    return $days . ' day' . ($days === 1 ? '' : 's') . ' left';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['action']) && $_POST['action'] === 'add_task') {
        $taskTitle = trim($_POST['task_title'] ?? '');
        $taskDescription = trim($_POST['task_description'] ?? '');
        $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
        $taskDueDate = trim($_POST['task_due_date'] ?? '');
        $taskPriority = in_array($_POST['task_priority'] ?? 'Medium', ['Low', 'Medium', 'High'], true) ? $_POST['task_priority'] : 'Medium';

        if ($taskTitle === '') {
            $error = 'Please enter a task title.';
        } else {
            $insertStmt = $conn->prepare("INSERT INTO todo_tasks (user_id, project_id, task_title, task_description, task_due_date, task_priority, task_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'Todo', NOW(), NOW())");
            $insertStmt->bind_param("iissss", $userId, $projectId, $taskTitle, $taskDescription, $taskDueDate, $taskPriority);
            if ($insertStmt->execute()) {
                $feedback = 'Task added successfully.';
            } else {
                $error = 'There was a problem adding the task. Please try again.';
            }
        }
    }

    if (!empty($_POST['action']) && $_POST['action'] === 'add_project') {
        $projectName = trim($_POST['project_name'] ?? '');
        $projectColor = trim($_POST['project_color'] ?? '');

        if ($projectName === '') {
            $error = 'Please enter a project name.';
        } else {
            $existingStmt = $conn->prepare("SELECT project_id FROM todo_projects WHERE user_id = ? AND project_status = 'Active' AND LOWER(project_name) = LOWER(?) LIMIT 1");
            $existingStmt->bind_param("is", $userId, $projectName);
            $existingStmt->execute();
            $existingProjectResult = $existingStmt->get_result();

            if ($existingProjectResult && $existingProjectResult->num_rows > 0) {
                $error = 'A project with this name already exists.';
            } else {
                $insertStmt = $conn->prepare("INSERT INTO todo_projects (user_id, project_name, project_color, project_status, created_at, updated_at) VALUES (?, ?, ?, 'Active', NOW(), NOW())");
                $insertStmt->bind_param("iss", $userId, $projectName, $projectColor);
                if ($insertStmt->execute()) {
                    header('Location: userdashboard.php');
                    exit();
                } else {
                    $error = 'Unable to create project. Please try again.';
                }
            }
        }
    }

    if (!empty($_POST['action']) && $_POST['action'] === 'delete_project' && !empty($_POST['project_id'])) {
        $projectId = intval($_POST['project_id']);
        $conn->query("UPDATE todo_tasks SET project_id = NULL WHERE project_id = '" . $projectId . "' AND user_id = '" . $userId . "'");
        if ($conn->query("DELETE FROM todo_projects WHERE project_id = '" . $projectId . "' AND user_id = '" . $userId . "'")) {
            $feedback = 'Project deleted successfully.';
        } else {
            $error = 'Unable to delete the project. Please try again.';
        }
    }

    if (!empty($_POST['action']) && $_POST['action'] === 'update_task_status' && !empty($_POST['task_id'])) {
        $taskId = intval($_POST['task_id']);
        $statusMap = ['done' => 'Done', 'todo' => 'Todo', 'in_progress' => 'In Progress'];
        $newStatus = $statusMap[$_POST['task_action'] ?? ''] ?? null;

        if ($newStatus) {
            $completedAt = ($newStatus === 'Done') ? 'NOW()' : 'NULL';
            $sql = "UPDATE todo_tasks SET task_status = '" . $newStatus . "', updated_at = NOW(), completed_at = " . $completedAt . " WHERE task_id = '" . $taskId . "' AND user_id = '" . $userId . "'";
            if ($conn->query($sql)) {
                $feedback = 'Task status updated.';
            } else {
                $error = 'Unable to update task status.';
            }
        }
    }

    if (!empty($_POST['action']) && $_POST['action'] === 'edit_task' && !empty($_POST['task_id'])) {
        $taskId = intval($_POST['task_id']);
        $taskTitle = trim($_POST['task_title'] ?? '');
        $taskDescription = trim($_POST['task_description'] ?? '');
        $taskDueDate = trim($_POST['task_due_date'] ?? '');
        $taskPriority = in_array($_POST['task_priority'] ?? 'Medium', ['Low', 'Medium', 'High'], true) ? $_POST['task_priority'] : 'Medium';
        $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;

        if ($taskTitle === '') {
            $error = 'Please enter a task title.';
        } else {
            $updateStmt = $conn->prepare("UPDATE todo_tasks SET task_title = ?, task_description = ?, task_due_date = ?, task_priority = ?, project_id = ?, updated_at = NOW() WHERE task_id = ? AND user_id = ?");
            $updateStmt->bind_param("ssssiii", $taskTitle, $taskDescription, $taskDueDate, $taskPriority, $projectId, $taskId, $userId);
            if ($updateStmt->execute()) {
                $feedback = 'Task updated successfully.';
            } else {
                $error = 'Unable to update the task. Please try again.';
            }
        }
    }

    if (!empty($_POST['action']) && $_POST['action'] === 'delete_task' && !empty($_POST['task_id'])) {
        $taskId = intval($_POST['task_id']);
        if ($conn->query("DELETE FROM todo_tasks WHERE task_id = '" . $taskId . "' AND user_id = '" . $userId . "'")) {
            $feedback = 'Task deleted successfully.';
        } else {
            $error = 'Unable to delete the task. Please try again.';
        }
    }
}

$today = date('Y-m-d');

$counts = [
    'total' => 0,
    'due_today' => 0,
    'completed' => 0,
    'overdue' => 0,
];

foreach ($counts as $key => &$val) {
    $sql = match($key) {
        'total' => "SELECT COUNT(*) FROM todo_tasks WHERE user_id = '" . $userId . "'",
        'due_today' => "SELECT COUNT(*) FROM todo_tasks WHERE user_id = '" . $userId . "' AND task_due_date = '" . $today . "' AND task_status <> 'Done'",
        'completed' => "SELECT COUNT(*) FROM todo_tasks WHERE user_id = '" . $userId . "' AND task_status = 'Done'",
        'overdue' => "SELECT COUNT(*) FROM todo_tasks WHERE user_id = '" . $userId . "' AND task_due_date < '" . $today . "' AND task_status <> 'Done'",
    };
    $result = $conn->query($sql);
    $val = intval($result->fetch_row()[0]);
}

$taskSql = "SELECT t.*, p.project_name FROM todo_tasks t LEFT JOIN todo_projects p ON t.project_id = p.project_id WHERE t.user_id = '" . $userId . "' ORDER BY FIELD(t.task_status, 'Overdue', 'Todo', 'In Progress', 'Done'), t.task_due_date IS NULL, t.task_due_date ASC";
$tasksResult = $conn->query($taskSql);
$tasks = $tasksResult->fetch_all(MYSQLI_ASSOC);

$finishedTasks = array_filter($tasks, fn($t) => $t['task_status'] === 'Done');
$ongoingTasks = array_filter($tasks, fn($t) => $t['task_status'] !== 'Done');
$overdueTasks = array_filter($tasks, fn($t) => $t['task_due_date'] < $today && $t['task_status'] <> 'Done');

$tasksByProject = [];
foreach ($tasks as $task) {
    $key = $task['project_id'] ? intval($task['project_id']) : 0;
    $tasksByProject[$key][] = $task;
}

$projectSql = "SELECT p.project_id, p.project_name, COUNT(t.task_id) AS total_tasks, SUM(t.task_status = 'Done') AS completed_tasks FROM todo_projects p LEFT JOIN todo_tasks t ON p.project_id = t.project_id AND t.user_id = '" . $userId . "' WHERE p.user_id = '" . $userId . "' AND p.project_status = 'Active' GROUP BY p.project_id ORDER BY p.project_name ASC";
$projects = $conn->query($projectSql)->fetch_all(MYSQLI_ASSOC);

$seenProjectNames = [];
$projects = array_filter($projects, function($project) use (&$seenProjectNames) {
    if (in_array($project['project_name'], $seenProjectNames)) {
        return false;
    }
    $seenProjectNames[] = $project['project_name'];
    return true;
});
// Re-index the array
$projects = array_values($projects);

$projectOptions = $conn->query("SELECT project_id, project_name FROM todo_projects WHERE user_id = '" . $userId . "' AND project_status = 'Active' ORDER BY project_name ASC")->fetch_all(MYSQLI_ASSOC);

$completionPercentage = $counts['total'] > 0 ? round(($counts['completed'] / $counts['total']) * 100) : 0;

$avgResult = $conn->query("SELECT AVG(DATEDIFF(completed_at, created_at)) AS avg_days FROM todo_tasks WHERE user_id = '" . $userId . "' AND task_status = 'Done' AND completed_at IS NOT NULL");
$avgAssoc = $avgResult->fetch_assoc();
$avgCompletionDays = ($avgAssoc && $avgAssoc['avg_days']) ? round($avgAssoc['avg_days'], 1) : 0;

$weeklyTrend = $conn->query("SELECT WEEK(completed_at) as week, YEAR(completed_at) as year, COUNT(*) as completed_count FROM todo_tasks WHERE user_id = '" . $userId . "' AND task_status = 'Done' AND completed_at IS NOT NULL AND completed_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK) GROUP BY YEAR(completed_at), WEEK(completed_at) ORDER BY year ASC, week ASC")->fetch_all(MYSQLI_ASSOC);

foreach ($projects as &$project) {
    $project['completed_tasks'] = intval($project['completed_tasks'] ?? 0);
    $project['total_tasks'] = intval($project['total_tasks'] ?? 0);
    $project['completion_rate'] = $project['total_tasks'] > 0 ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
    $project['status_breakdown'] = [];
    $statusResult = $conn->query("SELECT task_status, COUNT(*) as count FROM todo_tasks WHERE user_id = '" . $userId . "' AND project_id = '" . $project['project_id'] . "' GROUP BY task_status");
    while ($row = $statusResult->fetch_assoc()) {
        $project['status_breakdown'][$row['task_status']] = $row['count'];
    }
}
unset($project);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.4/font/bootstrap-icons.css" rel="stylesheet">
    <!-- <link rel="stylesheet" href="styles/custom.css"> -->
    <link rel="stylesheet" href="styles/user-dashboard.css">
</head>
<body>

<?php
function renderTaskTable($tasksData, $projectId = null) {
    global $today;
    ?>
    <div class="table-responsive">
        <table class="table align-middle table-hover">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Task</th>
                    <th>Project</th>
                    <th>Due</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th class="text-end pe-3">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tasksData) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No tasks found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tasksData as $task): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold"><?php echo htmlspecialchars($task['task_title']); ?></div>
                                <?php if ($task['task_description']): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars(substr($task['task_description'], 0, 60)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($task['project_name'] ?? 'No project'); ?></td>
                            <td>
                                <?php if ($task['task_due_date']): ?>
                                    <span class="due-toggle" data-due-text="<?php echo htmlspecialchars(date('M j, Y', strtotime($task['task_due_date']))); ?>" data-days-left="<?php echo htmlspecialchars(computeDaysLeftLabel($task['task_due_date'])); ?>"><?php echo htmlspecialchars(date('M j, Y', strtotime($task['task_due_date']))); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">No due date</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $task['task_priority'] === 'High' ? 'bg-danger' : ($task['task_priority'] === 'Medium' ? 'bg-primary' : 'bg-success'); ?>"><?php echo htmlspecialchars($task['task_priority']); ?></span></td>
                            <td><span class="badge <?php echo $task['task_status'] === 'Done' ? 'bg-success' : ($task['task_status'] === 'Overdue' ? 'bg-danger' : ($task['task_status'] === 'In Progress' ? 'bg-warning text-dark' : 'bg-secondary')); ?>"><?php echo htmlspecialchars($task['task_status']); ?></span></td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary edit-task-btn" data-task-id="<?php echo intval($task['task_id']); ?>">Edit</button>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="update_task_status">
                                        <input type="hidden" name="task_id" value="<?php echo intval($task['task_id']); ?>">
                                        <input type="hidden" name="task_action" value="<?php echo $task['task_status'] === 'Done' ? 'todo' : 'done'; ?>">
                                        <button type="submit" class="btn btn-outline-<?php echo $task['task_status'] === 'Done' ? 'secondary' : 'success'; ?>"><?php echo $task['task_status'] === 'Done' ? 'Reopen' : 'Done'; ?></button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this task?');">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="task_id" value="<?php echo intval($task['task_id']); ?>">
                                        <button type="submit" class="btn btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
<div class="user-dashboard-shell">
<nav class="navbar navbar-expand-lg user-navbar mb-4">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <span class="brand-mark"></span>
            <span>FlowState</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNav" aria-controls="userNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="userNav">
            <div class="ms-auto d-flex align-items-center gap-3 flex-column flex-sm-row text-end">
                <div class="me-3 d-none d-sm-block">
                    <p class="mb-0 text-uppercase small text-primary">Welcome back</p>
                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($_SESSION['full']); ?></h6>
                </div>
                <div class="dropdown">
                    <a class="btn btn-light profile-pill d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo htmlspecialchars($_SESSION['ca_ImgPath']); ?>" alt="User avatar">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="px-3 py-3">
                            <p class="text-uppercase small text-primary mb-1">Signed in as</p>
                            <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($_SESSION['userType']); ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid py-5">
    <div class="row g-3">
        <!-- Left Sidebar: Stats -->
        <div class="col-sm-3 col-lg-2 order-lg-1">
            <div class="sidebar-stats shadow-sm p-3" style="border-radius: 0.875rem; background-color: #ffffff; border: 1px solid var(--dashboard-border);">
                <!-- Total Tasks -->
                <div class="sidebar-stat-card">
                    <div class="d-flex align-items-start gap-2">
                        <div class="stat-icon-compact bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-list-task"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="card-label">Total Tasks</div>
                            <h4 class="mb-1"><?php echo intval($counts['total']); ?></h4>
                            <small class="text-muted">All tasks</small>
                        </div>
                    </div>
                </div>

                <!-- Due Today -->
                <div class="sidebar-stat-card">
                    <div class="d-flex align-items-start gap-2">
                        <div class="stat-icon-compact bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="card-label">Due Today</div>
                            <h4 class="mb-1"><?php echo intval($counts['due_today']); ?></h4>
                            <small class="text-muted">Before midnight</small>
                        </div>
                    </div>
                </div>

                <!-- Completed -->
                <div class="sidebar-stat-card">
                    <div class="d-flex align-items-start gap-2">
                        <div class="stat-icon-compact bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="card-label">Completed</div>
                            <h4 class="mb-1"><?php echo intval($counts['completed']); ?></h4>
                            <small class="text-muted">Finished</small>
                        </div>
                    </div>
                </div>

                <!-- Overdue -->
                <div class="sidebar-stat-card">
                    <div class="d-flex align-items-start gap-2">
                        <div class="stat-icon-compact bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="card-label">Overdue</div>
                            <h4 class="mb-1"><?php echo intval($counts['overdue']); ?></h4>
                            <small class="text-muted">Need attention</small>
                        </div>
                    </div>
                </div>

                <!-- Completion Rate -->
                <div class="sidebar-stat-card">
                    <div class="d-flex align-items-start gap-2">
                        <div class="stat-icon-compact bg-info bg-opacity-10 text-info">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="card-label">Completion</div>
                            <h4 class="mb-1"><?php echo intval($completionPercentage); ?>%</h4>
                            <small class="text-muted"><?php echo intval($counts['completed']); ?>/<?php echo intval($counts['total']); ?></small>
                        </div>
                    </div>
                </div>

                <!-- Avg Completion Time -->
                <div class="sidebar-stat-card">
                    <div class="d-flex align-items-start gap-2">
                        <div class="stat-icon-compact bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="card-label">Avg Time</div>
                            <h4 class="mb-1"><?php echo $avgCompletionDays; ?> <span style="font-size: 0.6em;">d</span></h4>
                            <small class="text-muted">Per task</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Main Content -->
        <div class="col-sm-9 col-lg-10 order-lg-2">
            <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="dashboard-card shadow-sm">
                <div class="card-header" style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                    <h5 class="mb-0" style="font-size: 1.1rem; font-weight: 600; letter-spacing: 0.5px;">📊 Project Insights</h5>
                </div>
                <div class="card-body" style="padding: 2rem;">
                    <?php if (count($projects) === 0): ?>
                        <p class="text-muted mb-0">No active projects yet. Create a project to see insights here.</p>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($projects as $project): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="p-4 border rounded" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); transition: all 0.3s ease;" onmouseover="this.style.boxShadow='0 8px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow='none'">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($project['project_name']); ?></h6>
                                            <span class="badge bg-primary" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;"><?php echo intval($project['completion_rate']); ?>%</span>
                                        </div>
                                        <div class="progress mb-3" style="height: 8px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo intval($project['completion_rate']); ?>%; background: linear-gradient(90deg, #3b82f6, #60a5fa);" aria-valuenow="<?php echo intval($project['completion_rate']); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted d-block mb-3">
                                            <strong><?php echo intval($project['completed_tasks']); ?></strong>/<strong><?php echo intval($project['total_tasks']); ?></strong> tasks completed
                                        </small>
                                        <div class="mt-3 pt-3 border-top">
                                            <div class="row text-center small">
                                                <div class="col">
                                                    <div class="text-muted">To-do</div>
                                                    <strong><?php echo intval($project['status_breakdown']['Todo'] ?? 0); ?></strong>
                                                </div>
                                                <div class="col">
                                                    <div class="text-muted">In Progress</div>
                                                    <strong><?php echo intval($project['status_breakdown']['In Progress'] ?? 0); ?></strong>
                                                </div>
                                                <div class="col">
                                                    <div class="text-muted">Done</div>
                                                    <strong><?php echo intval($project['status_breakdown']['Done'] ?? 0); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="dashboard-card shadow-sm">
                <div class="card-header" style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                    <h5 class="mb-0" style="font-size: 1.1rem; font-weight: 600; letter-spacing: 0.5px;">📈 Productivity Trends (Last 8 Weeks)</h5>
                </div>
                <div class="card-body" style="padding: 2rem;">
                    <?php if (count($weeklyTrend) === 0): ?>
                        <p class="text-muted mb-0">No completed tasks yet. Complete tasks to see your productivity trends.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Week</th>
                                        <th>Tasks Completed</th>
                                        <th>Visual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maxCompleted = max(array_column($weeklyTrend, 'completed_count'));
                                    foreach ($weeklyTrend as $trend): 
                                        $percentage = ($trend['completed_count'] / $maxCompleted) * 100;
                                        // Calculate week start (Monday) and end (Sunday)
                                        $year = intval($trend['year']);
                                        $week = intval($trend['week']);
                                        $weekStartDate = new DateTime();
                                        $weekStartDate->setISODate($year, $week, 1); // Monday
                                        $weekEndDate = clone $weekStartDate;
                                        $weekEndDate->modify('+6 days'); // Sunday
                                        $dateRange = $weekStartDate->format('M Y') . ' ' . $weekStartDate->format('j') . ' - ' . $weekEndDate->format('j');
                                    ?>
                                        <tr class="align-middle">
                                            <td class="ps-3"><strong><?php echo $dateRange; ?></strong></td>
                                            <td><span class="badge bg-success"><?php echo intval($trend['completed_count']); ?> tasks</span></td>
                                            <td class="pe-3">
                                                <div class="progress" style="height: 10px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo round($percentage); ?>%; background: linear-gradient(90deg, #10b981, #34d399) !important;" aria-valuenow="<?php echo round($percentage); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($overdueTasks) > 0): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="dashboard-card shadow-sm border-danger">
                <div class="card-header bg-danger bg-opacity-10" style="padding: 1.5rem; border-bottom: 2px solid #dc2626;">
                    <h5 class="mb-0 text-danger" style="font-size: 1.1rem; font-weight: 600; letter-spacing: 0.5px;"><i class="bi bi-exclamation-triangle me-2"></i>Overdue Tasks (<?php echo count($overdueTasks); ?>)</h5>
                </div>
                <div class="card-body" style="padding: 2rem;">
                    <div class="table-responsive" style="padding: 1rem 0;">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Task</th>
                                    <th>Project</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th class="pe-3">Priority</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueTasks as $task): 
                                    $dueDate = new DateTime($task['task_due_date']);
                                    $todayDate = new DateTime('today');
                                    $daysOverdue = $todayDate->diff($dueDate)->days;
                                ?>
                                    <tr class="table-danger table-opacity" style="padding: 1rem 0;">
                                        <td class="ps-3">
                                            <strong><?php echo htmlspecialchars($task['task_title']); ?></strong>
                                            <?php if ($task['task_description']): ?>
                                                <div class="small text-muted mt-1"><?php echo htmlspecialchars(substr($task['task_description'], 0, 50)); ?><?php echo strlen($task['task_description']) > 50 ? '...' : ''; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($task['project_name'] ?? 'No project'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($task['task_due_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-danger" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;"><?php echo $daysOverdue; ?> day<?php echo $daysOverdue !== 1 ? 's' : ''; ?></span>
                                        </td>
                                        <td class="pe-3"><span class="badge <?php echo $task['task_priority'] === 'High' ? 'bg-danger' : ($task['task_priority'] === 'Medium' ? 'bg-warning text-dark' : 'bg-info'); ?>"><?php echo htmlspecialchars($task['task_priority']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12">
            <div class="dashboard-card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3 gap-3">
                        <ul class="nav nav-tabs mb-0" id="todoTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-all-tab" data-bs-toggle="tab" data-bs-target="#tab-all" type="button" role="tab" aria-controls="tab-all" aria-selected="true">All Tasks</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-finished-tab" data-bs-toggle="tab" data-bs-target="#tab-finished" type="button" role="tab" aria-controls="tab-finished" aria-selected="false">Finished</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-ongoing-tab" data-bs-toggle="tab" data-bs-target="#tab-ongoing" type="button" role="tab" aria-controls="tab-ongoing" aria-selected="false">Ongoing</button>
                            </li>
                            <?php if (count($overdueTasks) > 0): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-overdue-tab" data-bs-toggle="tab" data-bs-target="#tab-overdue" type="button" role="tab" aria-controls="tab-overdue" aria-selected="false"><span class="badge bg-danger me-1"><?php echo count($overdueTasks); ?></span>Overdue</button>
                            </li>
                            <?php endif; ?>
                            <?php foreach ($projects as $project): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tab-project-<?php echo intval($project['project_id']); ?>-tab" data-bs-toggle="tab" data-bs-target="#tab-project-<?php echo intval($project['project_id']); ?>" type="button" role="tab" aria-controls="tab-project-<?php echo intval($project['project_id']); ?>" aria-selected="false"><?php echo htmlspecialchars($project['project_name']); ?></button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="d-flex align-items-center gap-2">
                            <button id="openTaskPopupBtn" type="button" class="btn btn-primary btn-sm">Add a Task</button>
                            <button id="openProjectPopupBtn" type="button" class="btn btn-outline-primary btn-sm">Create Project</button>
                            <button id="dueToggleBtn" type="button" class="btn btn-sm btn-outline-secondary due-toggle-btn" title="Currently showing due dates - click to show days left"><i class="bi bi-calendar-event"></i></button>
                        </div>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-all" role="tabpanel" aria-labelledby="tab-all-tab">
                            <?php renderTaskTable($tasks); ?>
                        </div>

                        <div class="tab-pane fade" id="tab-finished" role="tabpanel" aria-labelledby="tab-finished-tab">
                            <?php renderTaskTable($finishedTasks); ?>
                        </div>

                        <div class="tab-pane fade" id="tab-ongoing" role="tabpanel" aria-labelledby="tab-ongoing-tab">
                            <?php renderTaskTable($ongoingTasks); ?>
                        </div>

                        <?php if (count($overdueTasks) > 0): ?>
                        <div class="tab-pane fade" id="tab-overdue" role="tabpanel" aria-labelledby="tab-overdue-tab">
                            <?php renderTaskTable($overdueTasks); ?>
                        </div>
                        <?php endif; ?>

                        <?php foreach ($projects as $project): ?>
                        <div class="tab-pane fade" id="tab-project-<?php echo intval($project['project_id']); ?>" role="tabpanel" aria-labelledby="tab-project-<?php echo intval($project['project_id']); ?>-tab">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0"><?php echo htmlspecialchars($project['project_name']); ?></h5>
                                <form method="post" class="mb-0">
                                    <input type="hidden" name="action" value="delete_project">
                                    <input type="hidden" name="project_id" value="<?php echo intval($project['project_id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete project?');">Delete project</button>
                                </form>
                            </div>
                            <?php renderTaskTable($tasksByProject[intval($project['project_id'])] ?? []); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>
</div>

<div class="popup-overlay d-none" id="taskPopupOverlay">
    <div class="dashboard-card shadow-sm popup-card">
        <div class="card-body position-relative">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h5 class="mb-1">Quick add task</h5>
                    <p class="text-muted mb-0">Capture any new idea or task in seconds.</p>
                </div>
                <span class="badge bg-info text-dark">Fast</span>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_task">
                <div class="mb-3">
                    <label class="form-label">Task title</label>
                    <input type="text" name="task_title" class="form-control" placeholder="What do you need to do?" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="task_description" class="form-control" rows="3" placeholder="Notes, steps, or context"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="">No project</option>
                        <?php foreach ($projectOptions as $option): ?>
                            <option value="<?php echo intval($option['project_id']); ?>"><?php echo htmlspecialchars($option['project_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3 row g-2">
                    <div class="col-6">
                        <label class="form-label">Due date</label>
                        <input type="date" name="task_due_date" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Priority</label>
                        <select name="task_priority" class="form-select">
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Add task</button>
                    <button type="button" class="btn btn-outline-secondary" id="closeTaskPopupBtn">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="popup-overlay d-none" id="editTaskPopupOverlay">
    <div class="dashboard-card shadow-sm popup-card">
        <div class="card-body position-relative">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h5 class="mb-1">Edit task</h5>
                    <p class="text-muted mb-0">Update task details and properties.</p>
                </div>
                <span class="badge bg-warning text-dark">Update</span>
            </div>
            <form method="post" id="editTaskForm">
                <input type="hidden" name="action" value="edit_task">
                <input type="hidden" name="task_id" id="editTaskId" value="">
                <div class="mb-3">
                    <label class="form-label">Task title</label>
                    <input type="text" name="task_title" id="editTaskTitle" class="form-control" placeholder="What do you need to do?" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="task_description" id="editTaskDescription" class="form-control" rows="3" placeholder="Notes, steps, or context"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Project</label>
                    <select name="project_id" id="editTaskProject" class="form-select">
                        <option value="">No project</option>
                        <?php foreach ($projectOptions as $option): ?>
                            <option value="<?php echo intval($option['project_id']); ?>"><?php echo htmlspecialchars($option['project_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3 row g-2">
                    <div class="col-6">
                        <label class="form-label">Due date</label>
                        <input type="date" name="task_due_date" id="editTaskDueDate" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Priority</label>
                        <select name="task_priority" id="editTaskPriority" class="form-select">
                            <option value="High">High</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Update task</button>
                    <button type="button" class="btn btn-outline-secondary" id="closeEditTaskPopupBtn">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="popup-overlay d-none" id="projectPopupOverlay">
    <div class="dashboard-card shadow-sm popup-card">
        <div class="card-body position-relative">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h5 class="mb-1">Create project</h5>
                    <p class="text-muted mb-0">Create a project tab and group tasks automatically.</p>
                </div>
                <span class="badge bg-primary text-white">New</span>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_project">
                <div class="mb-3">
                    <label class="form-label">Project name</label>
                    <input type="text" name="project_name" class="form-control" placeholder="Project name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Color tag</label>
                    <input type="text" name="project_color" class="form-control" placeholder="Optional color label">
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Create project</button>
                    <button type="button" class="btn btn-outline-secondary" id="closeProjectPopupBtn">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Store all tasks data for edit modal
    const tasksData = <?php echo json_encode($tasks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        var toggles = document.querySelectorAll('.due-toggle-btn');
        var dueCells = document.querySelectorAll('.due-toggle');
        var showDays = false;

        function updateDueDisplay() {
            dueCells.forEach(function (cell) {
                var dueText = cell.dataset.dueText;
                var daysText = cell.dataset.daysLeft;
                if (!dueText) {
                    return;
                }
                cell.textContent = showDays ? daysText : dueText;
            });
            toggles.forEach(function (btn) {
                var icon = btn.querySelector('i');
                if (icon) {
                    if (showDays) {
                        icon.className = 'bi bi-hourglass';
                        btn.title = 'Currently showing days left - click to show due dates';
                    } else {
                        icon.className = 'bi bi-calendar-event';
                        btn.title = 'Currently showing due dates - click to show days left';
                    }
                }
            });
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                showDays = !showDays;
                updateDueDisplay();
            });
        });

        var taskPopupOverlay = document.getElementById('taskPopupOverlay');
        var openTaskPopupBtn = document.getElementById('openTaskPopupBtn');
        var closeTaskPopupBtn = document.getElementById('closeTaskPopupBtn');
        
        var editTaskPopupOverlay = document.getElementById('editTaskPopupOverlay');
        var closeEditTaskPopupBtn = document.getElementById('closeEditTaskPopupBtn');
        var editTaskForm = document.getElementById('editTaskForm');
        
        var projectPopupOverlay = document.getElementById('projectPopupOverlay');
        var openProjectPopupBtn = document.getElementById('openProjectPopupBtn');
        var closeProjectPopupBtn = document.getElementById('closeProjectPopupBtn');

        function togglePopup(popup, show) {
            if (!popup) return;
            popup.classList.toggle('show', show);
            popup.classList.toggle('d-none', !show);
            if (show) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        // Edit task handlers
        var editTaskBtns = document.querySelectorAll('.edit-task-btn');
        editTaskBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var taskId = parseInt(this.dataset.taskId);
                var task = tasksData.find(t => parseInt(t.task_id) === taskId);
                
                if (task) {
                    document.getElementById('editTaskId').value = task.task_id;
                    document.getElementById('editTaskTitle').value = task.task_title || '';
                    document.getElementById('editTaskDescription').value = task.task_description || '';
                    document.getElementById('editTaskProject').value = task.project_id || '';
                    document.getElementById('editTaskDueDate').value = task.task_due_date || '';
                    document.getElementById('editTaskPriority').value = task.task_priority || 'Medium';
                } else {
                    // Fallback: at least set the task ID if task data not found
                    document.getElementById('editTaskId').value = taskId;
                    document.getElementById('editTaskTitle').value = '';
                    document.getElementById('editTaskDescription').value = '';
                    document.getElementById('editTaskProject').value = '';
                    document.getElementById('editTaskDueDate').value = '';
                    document.getElementById('editTaskPriority').value = 'Medium';
                    console.warn('Task with ID ' + taskId + ' not found in tasksData');
                }
                togglePopup(editTaskPopupOverlay, true);
            });
        });

        if (closeEditTaskPopupBtn) {
            closeEditTaskPopupBtn.addEventListener('click', function () {
                togglePopup(editTaskPopupOverlay, false);
            });
        }

        if (editTaskPopupOverlay) {
            editTaskPopupOverlay.addEventListener('click', function (event) {
                if (event.target === editTaskPopupOverlay) {
                    togglePopup(editTaskPopupOverlay, false);
                }
            });
        }

        if (openTaskPopupBtn) {
            openTaskPopupBtn.addEventListener('click', function () {
                togglePopup(taskPopupOverlay, true);
            });
        }

        if (closeTaskPopupBtn) {
            closeTaskPopupBtn.addEventListener('click', function () {
                togglePopup(taskPopupOverlay, false);
            });
        }

        if (openProjectPopupBtn) {
            openProjectPopupBtn.addEventListener('click', function () {
                togglePopup(projectPopupOverlay, true);
            });
        }

        if (closeProjectPopupBtn) {
            closeProjectPopupBtn.addEventListener('click', function () {
                togglePopup(projectPopupOverlay, false);
            });
        }

        if (taskPopupOverlay) {
            taskPopupOverlay.addEventListener('click', function (event) {
                if (event.target === taskPopupOverlay) {
                    togglePopup(taskPopupOverlay, false);
                }
            });
        }

        if (projectPopupOverlay) {
            projectPopupOverlay.addEventListener('click', function (event) {
                if (event.target === projectPopupOverlay) {
                    togglePopup(projectPopupOverlay, false);
                }
            });
        }
    });
</script>
</body>
</html>