<?php
require_once '../config/config.php';
require_once '../libs/Db.php';
require_once '../libs/Session.php';

// Initialize session
Session::init();

// Check if user is admin (simplified for now)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize database
$db = new Db();

// Get dashboard statistics
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Get basic reservation stats (simplified)
$todayReservations = $db->query("SELECT COUNT(*) as count FROM reservations WHERE reservation_date = ?", [$today])->fetch();
$monthlyReservations = $db->query("SELECT COUNT(*) as count FROM reservations WHERE reservation_date BETWEEN ? AND ?", [$monthStart, $monthEnd])->fetch();

// Get real-time notifications (simplified)
$notifications = [];
$lowStockItems = $db->query("SELECT COUNT(*) as count FROM food WHERE stock_quantity < 10")->fetch();

if ($lowStockItems && $lowStockItems['count'] > 0) {
    $notifications[] = [
        'type' => 'danger',
        'message' => "{$lowStockItems['count']} items low on stock",
        'icon' => 'fa-exclamation-circle'
    ];
}

// Get recent reservations
$recentReservations = $db->query("SELECT * FROM reservations ORDER BY reservation_date DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Food Chef Cafe</title>
    <link rel="stylesheet" href="../public/css/bootstrap.css">
    <link rel="stylesheet" href="../public/css/font-awesome.css">
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/custom.css">
    <style>
        .dashboard-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .quick-action {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .reservation-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
        }
        .status-pending { border-left-color: #ffc107; }
        .status-confirmed { border-left-color: #28a745; }
        .status-cancelled { border-left-color: #dc3545; }
        .notification-item {
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .notification-warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .notification-danger {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Food Chef Admin</a>
            <div class="navbar-nav ml-auto">
                <span class="navbar-text mr-3">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                </span>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action active">
                        <i class="fa fa-dashboard"></i> Dashboard
                    </a>
                    <a href="reservations.php" class="list-group-item list-group-item-action">
                        <i class="fa fa-calendar"></i> Reservations
                    </a>
                    <a href="food.php" class="list-group-item list-group-item-action">
                        <i class="fa fa-cutlery"></i> Food Management
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fa fa-users"></i> Users
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="fa fa-cog"></i> Settings
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <h2 class="mb-4">Dashboard Overview</h2>

                <!-- Notifications -->
                <?php if (!empty($notifications)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fa fa-bell"></i> Notifications</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item notification-<?php echo $notification['type']; ?>">
                                        <i class="fa <?php echo $notification['icon']; ?>"></i>
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <div class="stats-number"><?php echo $todayReservations['count'] ?? 0; ?></div>
                            <div>Today's Reservations</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <div class="stats-number"><?php echo $monthlyReservations['count'] ?? 0; ?></div>
                            <div>Monthly Total</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <div class="stats-number">0</div>
                            <div>Confirmed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <div class="stats-number">0</div>
                            <div>Avg Guests</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4>Quick Actions</h4>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="quick-action" onclick="location.href='reservations.php?action=new'">
                                    <i class="fa fa-plus fa-2x text-primary mb-2"></i>
                                    <div>New Reservation</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="quick-action" onclick="location.href='food.php?action=add'">
                                    <i class="fa fa-cutlery fa-2x text-success mb-2"></i>
                                    <div>Add Food Item</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="quick-action" onclick="location.href='reports.php'">
                                    <i class="fa fa-chart-bar fa-2x text-info mb-2"></i>
                                    <div>View Reports</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="quick-action" onclick="location.href='settings.php'">
                                    <i class="fa fa-cog fa-2x text-warning mb-2"></i>
                                    <div>System Settings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Reservations -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Reservations</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentReservations)): ?>
                                    <p class="text-muted">No recent reservations</p>
                                <?php else: ?>
                                    <?php foreach ($recentReservations as $reservation): ?>
                                        <div class="reservation-item status-<?php echo $reservation['status'] ?? 'pending'; ?>">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($reservation['name']); ?></strong>
                                                    <br>
                                                    <small><?php echo $reservation['reservation_date']; ?> - <?php echo $reservation['guests']; ?> guests</small>
                                                </div>
                                                <span class="badge badge-<?php echo ($reservation['status'] ?? 'pending') === 'confirmed' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($reservation['status'] ?? 'pending'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>System Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="h4 text-success">Online</div>
                                            <div class="text-muted">Database</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="h4 text-success">Active</div>
                                            <div class="text-muted">System</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../public/js/jquery-2.1.4.min.js"></script>
    <script src="../public/js/bootstrap.js"></script>
    <script>
        // Add some interactivity
        $('.quick-action').click(function() {
            $(this).addClass('bg-light');
            setTimeout(() => {
                $(this).removeClass('bg-light');
            }, 200);
        });
    </script>
</body>
</html>
