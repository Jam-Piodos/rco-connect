<?php
// Get top clubs ranked by number of events created
$conn = getDBConnection();
$query = "
    SELECT 
        u.id, 
        u.club_name, 
        u.description,
        COUNT(e.id) as event_count 
    FROM 
        users u 
    LEFT JOIN 
        events e ON u.id = e.created_by 
    WHERE 
        u.role = 'user'
    GROUP BY 
        u.id 
    ORDER BY 
        event_count DESC, 
        u.created_at ASC
    LIMIT 10
";
$result = $conn->query($query);
$top_clubs = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="top-clubs-container">
    <div class="top-clubs-title">TOP PERFORMING RCOs</div>
    
    <?php if (empty($top_clubs)): ?>
        <div class="no-clubs">No clubs have created events yet.</div>
    <?php else: ?>
        <div class="club-rankings">
            <?php 
            $rank = 1;
            foreach ($top_clubs as $club): 
                // Skip clubs with 0 events
                if ($club['event_count'] == 0 && $rank > 3) continue;
            ?>
                <div class="club-card <?php echo ($rank <= 3) ? 'top-' . $rank : ''; ?>">
                    <div class="club-rank"><?php echo $rank; ?></div>
                    <div class="club-avatar">
                        <?php echo strtoupper(substr($club['club_name'], 0, 1)); ?>
                    </div>
                    <div class="club-info">
                        <h3 class="club-name"><?php echo htmlspecialchars($club['club_name']); ?></h3>
                        <p class="club-description">
                            <?php 
                                $desc = $club['description'] ? $club['description'] : 'No description available.';
                                echo (strlen($desc) > 100) ? htmlspecialchars(substr($desc, 0, 100)) . '...' : htmlspecialchars($desc); 
                            ?>
                        </p>
                    </div>
                    <div class="club-stats">
                        <div class="event-count"><?php echo $club['event_count']; ?></div>
                        <div class="event-label">Events</div>
                    </div>
                </div>
            <?php 
                $rank++;
            endforeach; 
            ?>
        </div>
    <?php endif; ?>
    
    <div class="charts-container">
        <div class="chart-box">
            <canvas id="topClubsChart"></canvas>
        </div>
        <div class="chart-box">
            <canvas id="eventsActivityChart"></canvas>
        </div>
    </div>
</div>

<style>
.top-clubs-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.top-clubs-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 25px;
    color: #333;
    text-align: center;
}

.club-rankings {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 40px;
}

.club-card {
    display: flex;
    align-items: center;
    background: #f9f9f9;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: transform 0.2s;
    position: relative;
}

.club-card:hover {
    transform: translateY(-2px);
}

.club-card.top-1 {
    background: linear-gradient(to right, #fef9e3, #fff);
    border-left: 4px solid #ffd700;
}

.club-card.top-2 {
    background: linear-gradient(to right, #f8f8f8, #fff);
    border-left: 4px solid #c0c0c0;
}

.club-card.top-3 {
    background: linear-gradient(to right, #fdf1e5, #fff);
    border-left: 4px solid #cd7f32;
}

.club-rank {
    position: absolute;
    top: -10px;
    left: -10px;
    width: 30px;
    height: 30px;
    background: #ff9800;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.club-card.top-1 .club-rank {
    background: #ffd700;
    color: #333;
}

.club-card.top-2 .club-rank {
    background: #c0c0c0;
    color: #333;
}

.club-card.top-3 .club-rank {
    background: #cd7f32;
    color: white;
}

.club-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #ff9800;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    margin-right: 15px;
}

.club-info {
    flex: 1;
}

.club-name {
    margin: 0 0 5px 0;
    font-size: 1.1rem;
    color: #333;
}

.club-description {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.club-stats {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-left: 20px;
}

.event-count {
    font-size: 1.8rem;
    font-weight: bold;
    color: #ff9800;
}

.event-label {
    font-size: 0.8rem;
    color: #777;
}

.no-clubs {
    text-align: center;
    padding: 30px;
    background: #f5f5f5;
    border-radius: 8px;
    color: #666;
    font-style: italic;
}

.charts-container {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
    justify-content: center;
    margin-top: 40px;
}

.chart-box {
    width: 45%;
    min-width: 300px;
    background: #f9f9f9;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

@media (max-width: 768px) {
    .charts-container {
        flex-direction: column;
        align-items: center;
    }
    
    .chart-box {
        width: 100%;
    }
}
</style>

<script>
// Initialize charts when this component loads
document.addEventListener('DOMContentLoaded', function() {
    // Get top club data for chart
    const clubData = <?php 
        $chartData = array_slice($top_clubs, 0, 5);
        $labels = array_map(function($club) { return $club['club_name']; }, $chartData);
        $data = array_map(function($club) { return $club['event_count']; }, $chartData);
        echo json_encode(['labels' => $labels, 'data' => $data]); 
    ?>;

    // Top Clubs Donut Chart
    if (document.getElementById('topClubsChart')) {
        const topClubsChart = new Chart(document.getElementById('topClubsChart'), {
            type: 'doughnut',
            data: {
                labels: clubData.labels,
                datasets: [{
                    data: clubData.data,
                    backgroundColor: [
                        '#3575ec', // blue
                        '#a020f0', // purple
                        '#3cb371', // green
                        '#ff9800', // orange
                        '#e74c3c'  // red
                    ],
                    borderWidth: 2,
                }]
            },
            options: {
                cutout: '60%',
                plugins: {
                    legend: { 
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: { enabled: true }
                }
            }
        });
    }

    // Activity Bar Chart
    if (document.getElementById('eventsActivityChart')) {
        // Generate random data for demonstration
        const monthlyData = [
            Math.floor(Math.random() * 10) + 5,
            Math.floor(Math.random() * 10) + 8,
            Math.floor(Math.random() * 10) + 15,
            Math.floor(Math.random() * 10) + 20
        ];
        
        new Chart(document.getElementById('eventsActivityChart'), {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Events This Month',
                    data: monthlyData,
                    backgroundColor: [
                        '#a66a2c', // brown
                        '#bdbdbd', // gray
                        '#ffe033', // yellow
                        '#ff9800'  // orange
                    ],
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.7
                }]
            },
            options: {
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eee' },
                        ticks: { stepSize: 5 }
                    }
                }
            }
        });
    }
});
</script> 