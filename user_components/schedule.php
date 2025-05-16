<?php
// Get upcoming events from database for the calendar
require_once '../config.php';
$conn = getDBConnection();

// Get current month and year
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get first day of the month
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$month_name = date('F', $first_day);
$days_in_month = date('t', $first_day);
$start_day = date('N', $first_day); // 1 (Mon) to 7 (Sun)

// Get events for the current user
$stmt = $conn->prepare("SELECT * FROM events WHERE created_by = ? ORDER BY event_date ASC");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Group events by date
$events_by_date = [];
foreach ($events as $event) {
    $date = date('Y-m-d', strtotime($event['event_date']));
    if (!isset($events_by_date[$date])) {
        $events_by_date[$date] = [];
    }
    $events_by_date[$date][] = $event;
}
?>

<div class="schedule-container">
    <div class="dashboard-title">SCHEDULE</div>
    
    <div class="calendar-and-form">
        <div class="calendar-container">
            <div class="calendar-header">
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
                    for ($i = $start_day; $i <= 7 && $day_count <= $days_in_month; $i++) {
                        $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day_count);
                        $classes = [];
                        
                        if (isset($events_by_date[$date])) {
                            $classes[] = 'has-events';
                        }
                        
                        if ($day_count == $today_day && $current_month == $today_month && $current_year == $today_year) {
                            $classes[] = 'today';
                        }
                        
                        $class_attr = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
                        echo "<td{$class_attr} data-date=\"{$date}\">{$day_count}</td>";
                        
                        $day_count++;
                    }
                    
                    echo "</tr>";
                    
                    // Continue with remaining weeks
                    while ($day_count <= $days_in_month) {
                        echo "<tr>";
                        
                        for ($i = 1; $i <= 7 && $day_count <= $days_in_month; $i++) {
                            $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day_count);
                            $classes = [];
                            
                            if (isset($events_by_date[$date])) {
                                $classes[] = 'has-events';
                            }
                            
                            if ($day_count == $today_day && $current_month == $today_month && $current_year == $today_year) {
                                $classes[] = 'today';
                            }
                            
                            $class_attr = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
                            echo "<td{$class_attr} data-date=\"{$date}\">{$day_count}</td>";
                            
                            $day_count++;
                        }
                        
                        // Fill in empty cells at the end
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
        
        <div class="event-details-container">
            <h3>Selected Day Events</h3>
            <div id="selected-day-events">
                <p class="no-events-message">Select a day to view events</p>
            </div>
        </div>
    </div>
</div>

<style>
.schedule-container {
    padding: 20px;
    max-width: 1000px;
    margin: 0 auto;
}

.calendar-and-form {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
    justify-content: center;
}

.calendar-container {
    background: white;
    border-radius: 16px;
    padding: 0;
    width: 450px;
    min-width: 300px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.08);
}

.calendar-header {
    background: #ff9800;
    color: #fff;
    border-radius: 16px 16px 0 0;
    padding: 20px 0;
    text-align: center;
    font-weight: 600;
    position: relative;
}

.calendar-table {
    width: 100%;
    border-collapse: collapse;
}

.calendar-table th, .calendar-table td {
    width: 14.28%;
    text-align: center;
    padding: 12px 0;
}

.calendar-table th {
    color: #888;
    font-weight: 500;
}

.calendar-table td {
    color: #222;
    font-weight: 500;
    cursor: pointer;
}

.calendar-table td.has-events {
    background: #fff3e0;
    font-weight: bold;
    cursor: pointer;
}

.calendar-table td.today {
    border: 2px solid #ff9800;
}

.calendar-table td.selected {
    background: #ff9800;
    color: white;
}

.event-details-container {
    background: white;
    border-radius: 16px;
    padding: 24px;
    width: 400px;
    min-width: 300px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.08);
}

.no-events-message {
    color: #888;
    font-style: italic;
    text-align: center;
}

.event-item {
    background: #f5f5f5;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.event-item h4 {
    color: #333;
    margin-top: 0;
    margin-bottom: 8px;
}

.event-meta {
    color: #666;
    font-size: 0.9em;
    margin-top: 10px;
}

@media (max-width: 900px) {
    .calendar-container, .event-details-container {
        width: 100%;
    }
}
</style>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Calendar day click handler
    const calendarDays = document.querySelectorAll('.calendar-table td[data-date]');
    const selectedDayEvents = document.getElementById('selected-day-events');
    
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            // Remove selected class from all days
            document.querySelectorAll('.calendar-table td').forEach(td => {
                td.classList.remove('selected');
            });
            
            // Add selected class to clicked day
            this.classList.add('selected');
            
            // Get date from data attribute
            const date = this.getAttribute('data-date');
            
            // Show events for selected date
            const eventsData = <?php echo json_encode($events_by_date); ?>;
            
            if (eventsData[date] && eventsData[date].length > 0) {
                let eventsHtml = '';
                
                eventsData[date].forEach(event => {
                    eventsHtml += `
                        <div class="event-item">
                            <h4>${event.title}</h4>
                            <p>${event.description || 'No description'}</p>
                            <div class="event-meta">
                                <span>Time: ${formatTime(event.event_time)}</span><br>
                                <span>Location: ${event.location}</span>
                            </div>
                        </div>
                    `;
                });
                
                selectedDayEvents.innerHTML = eventsHtml;
            } else {
                selectedDayEvents.innerHTML = '<p class="no-events-message">No events for this day</p>';
            }
        });
    });
    
    // Helper function to format time
    function formatTime(timeStr) {
        const [hours, minutes] = timeStr.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }
        });
    </script>