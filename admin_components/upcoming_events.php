<?php
session_start();
// Only allow admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
    exit();
}
// Strict security check - must be logged in AND verified
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_verified']) || $_SESSION['is_verified'] !== true) {
    $_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];
    header('Location: ../login.php');
    exit();
}

require '../config.php';

// Get upcoming events
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT e.*, u.club_name 
    FROM events e 
    LEFT JOIN users u ON e.created_by = u.id 
    WHERE e.event_date >= CURDATE() 
    ORDER BY e.event_date ASC
");
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Get current month and year for calendar display
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Ensure valid month
if ($current_month < 1) {
    $current_month = 12;
    $current_year--;
} elseif ($current_month > 12) {
    $current_month = 1;
    $current_year++;
}

$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get first day of the month
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$month_name = date('F', $first_day);
$days_in_month = date('t', $first_day);
$start_day = date('N', $first_day); // 1 (Mon) to 7 (Sun)

// Get events for the current month
$conn = getDBConnection();
$start_date = "$current_year-$current_month-01";
$end_date = date('Y-m-t', strtotime($start_date));

$stmt = $conn->prepare("
    SELECT id, title, event_date, start_time, end_time
    FROM events 
    WHERE event_date BETWEEN ? AND ?
    ORDER BY event_date, start_time
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$month_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Organize events by day
$events_by_day = [];
foreach ($month_events as $event) {
    $day = (int)date('j', strtotime($event['event_date']));
    if (!isset($events_by_day[$day])) {
        $events_by_day[$day] = [];
    }
    $events_by_day[$day][] = $event;
}

// Include admin header
include 'admin_header.php';
?>

<div class="main-content">
    <div class="calendar-container">
        <div class="calendar-header">
            <div class="calendar-nav">
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="calendar-nav-btn">&#8249;</a>
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="calendar-nav-btn">&#8250;</a>
            </div>
            <div class="month"><?php echo strtoupper($month_name); ?></div>
            <div class="year"><?php echo $current_year; ?></div>
        </div>
        <table class="calendar-table">
            <thead>
                <tr>
                    <th>Mo</th><th>Tu</th><th>We</th><th>Th</th><th>Fr</th><th>Sa</th><th>Su</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Calendar generation
                $day_count = 1;
                $today_day = date('j');
                $today_month = date('n');
                $today_year = date('Y');
                
                echo "<tr>";
                
                // Fill in empty cells before the first day of the month
                for ($i = 1; $i < $start_day; $i++) {
                    echo "<td></td>";
                }
                
                // Fill in days of the month
                for ($i = $start_day; $i <= 7; $i++) {
                    $class = [];
                    if (isset($events_by_day[$day_count])) {
                        $class[] = 'has-events';
                    }
                    if ($day_count == $today_day && $current_month == $today_month && $current_year == $today_year) {
                        $class[] = 'today';
                    }
                    
                    echo "<td " . (!empty($class) ? 'class="' . implode(' ', $class) . '"' : '') . " data-day=\"$day_count\">";
                    echo $day_count;
                    if (isset($events_by_day[$day_count])) {
                        echo "<span class=\"event-indicator\"></span>";
                    }
                    echo "</td>";
                    $day_count++;
                }
                
                // Continue with the rest of the days
                while ($day_count <= $days_in_month) {
                    echo "<tr>";
                    for ($i = 1; $i <= 7 && $day_count <= $days_in_month; $i++) {
                        $class = [];
                        if (isset($events_by_day[$day_count])) {
                            $class[] = 'has-events';
                        }
                        if ($day_count == $today_day && $current_month == $today_month && $current_year == $today_year) {
                            $class[] = 'today';
                        }
                        
                        echo "<td " . (!empty($class) ? 'class="' . implode(' ', $class) . '"' : '') . " data-day=\"$day_count\">";
                        echo $day_count;
                        if (isset($events_by_day[$day_count])) {
                            echo "<span class=\"event-indicator\"></span>";
                        }
                        echo "</td>";
                        $day_count++;
                    }
                    // Fill remaining cells if month ends before the week
                    while ($i <= 7) {
                        echo "<td></td>";
                        $i++;
                    }
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <div class="activities-container">
        <div class="activities-title">UPCOMING EVENTS</div>
        <div class="events-list">
            <?php if (empty($events)): ?>
                <div class="event-item">
                    <p>No upcoming events scheduled</p>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($events, 0, 5) as $event): ?>
                    <div class="event-item">
                        <div class="event-date"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></div>
                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                        <div class="event-details">
                            <?php echo date('h:i A', strtotime($event['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($event['end_time'])); ?> 
                            at <?php echo htmlspecialchars($event['location']); ?>
                        </div>
                        <div class="event-club">By: <?php echo htmlspecialchars($event['club_name'] ?? 'Unknown'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
        .calendar-container {
            background: none;
            border-radius: 12px;
            padding: 0;
            width: 500px;
            min-width: 340px;
            max-width: 600px;
        }
        .calendar-header {
            background: #ff9800;
            color: #fff;
            border-radius: 12px 12px 0 0;
            padding: 32px 0 16px 0;
            text-align: center;
            font-size: 1.3rem;
            font-weight: 600;
            letter-spacing: 2px;
    position: relative;
        }
        .calendar-header .month {
            font-size: 1.2rem;
            font-weight: 600;
        }
        .calendar-header .year {
            font-size: 1rem;
            font-weight: 400;
            margin-top: 2px;
        }
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            background: #f5f5f5;
            border-radius: 0 0 12px 12px;
            overflow: hidden;
        }
        .calendar-table th, .calendar-table td {
            width: 14.28%;
            text-align: center;
            padding: 8px 0;
            font-size: 1rem;
        }
        .calendar-table th {
            color: #888;
            font-weight: 500;
        }
        .calendar-table td {
            color: #222;
            font-weight: 500;
    position: relative;
    height: 40px;
}
.calendar-table td.has-events {
    background: #fff3e0;
    cursor: pointer;
        }
        .calendar-table td.selected {
            background: #ff9800;
            color: #fff;
}
.calendar-table td.today {
    font-weight: bold;
    border: 2px solid #ff9800;
}
.calendar-table td .event-indicator {
    position: absolute;
    bottom: 2px;
    left: 50%;
    transform: translateX(-50%);
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ff9800;
        }
        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
    width: 100%;
    padding: 0 16px;
    box-sizing: border-box;
            position: absolute;
    top: 16px;
        }
        .calendar-nav-btn {
            background: none;
            border: none;
            color: #fff;
    font-size: 1.5rem;
            cursor: pointer;
    padding: 0;
    margin: 0;
    text-decoration: none;
        }
        .activities-container {
            background: #fafaff;
            border-radius: 12px;
            padding: 24px 32px 24px 32px;
            min-width: 320px;
            max-width: 340px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .activities-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 18px;
            color: #222;
        }
.events-list {
            list-style: none;
            padding: 0;
            margin: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.event-item {
    background: white;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.event-date {
    font-size: 0.9rem;
    color: #ff9800;
    font-weight: 600;
}
.event-title {
    font-weight: 600;
    margin: 6px 0;
}
.event-details {
    color: #666;
    font-size: 0.9rem;
}
.event-club {
    font-size: 0.85rem;
    color: #888;
    font-style: italic;
    margin-top: 6px;
}
.main-content {
    margin-top: 32px;
            display: flex;
            justify-content: center;
    align-items: flex-start;
    gap: 48px;
        }
        @media (max-width: 900px) {
            .main-content {
                flex-direction: column;
                align-items: center;
                gap: 32px;
            }
            .calendar-container, .activities-container {
                width: 90vw;
                min-width: unset;
                max-width: 98vw;
            }
        }
        .page-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 15px;
            width: 100%;
            max-width: 1100px;
        }
        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            background-color: #f5f5f5;
            color: #555;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .action-link:hover {
            background-color: #eeeeee;
            color: #222;
        }
        .profile-link {
            background-color: #f0f8ff;
        }
        .profile-link:hover {
            background-color: #e0f0ff;
        }
        .archive-link {
            background-color: #fff8f0;
        }
        .archive-link:hover {
            background-color: #ffe8c6;
        }
    </style>

    <script>
// Calendar day click to show events for that day
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.calendar-table td').forEach(td => {
        td.addEventListener('click', function() {
            const day = this.getAttribute('data-day');
            if (day) {
                document.querySelectorAll('.calendar-table td.selected').forEach(el => {
                    el.classList.remove('selected');
                });
                this.classList.add('selected');
                
                // You can add logic here to show events for the selected day
                // For example, update the activities container with day-specific events
            }
        });
    });
        });
    </script>