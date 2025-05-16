<?php
/**
 * RCO Ranking Data
 * This file provides data for the RCO rankings displayed in charts and lists
 */

require_once dirname(__DIR__) . '/config.php';

/**
 * Get top performing RCOs data
 * 
 * @param int $limit Number of top RCOs to retrieve
 * @return array Array containing RCO data for charts and lists
 */
function getTopRCOsData($limit = 5) {
    $conn = getDBConnection();
    
    // This query would be replaced with your actual ranking logic based on your database schema
    // For example, you might calculate rankings based on activity count, events created, etc.
    $query = "SELECT 
                u.id,
                u.club_name,
                u.description,
                COUNT(e.id) as event_count,
                COALESCE(SUM(CASE WHEN e.event_date >= CURDATE() THEN 1 ELSE 0 END), 0) as upcoming_events
              FROM 
                users u
              LEFT JOIN 
                events e ON u.id = e.created_by
              WHERE 
                u.role = 'user'
              GROUP BY 
                u.id
              ORDER BY 
                event_count DESC, upcoming_events DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rcos = [];
    $labels = [];
    $data = [];
    $colors = [
        '#3575ec', // blue
        '#a020f0', // purple
        '#3cb371', // green
        '#ff9800', // orange
        '#e74c3c', // red
        '#9c27b0', // deep purple
        '#00bcd4', // cyan
        '#4caf50', // light green
        '#795548', // brown
        '#607d8b'  // blue gray
    ];
    
    $total_events = 0;
    $rank = 0;
    
    while ($row = $result->fetch_assoc()) {
        $rank++;
        $rcos[] = [
            'rank' => $rank,
            'name' => $row['club_name'],
            'description' => $row['description'],
            'event_count' => $row['event_count'],
            'upcoming_events' => $row['upcoming_events']
        ];
        
        $labels[] = $row['club_name'];
        $data[] = (int)$row['event_count'];
        $total_events += (int)$row['event_count'];
    }
    
    // Calculate percentages for the donut chart
    $percentages = [];
    if ($total_events > 0) {
        foreach ($data as $value) {
            $percentages[] = round(($value / $total_events) * 100, 1);
        }
    } else {
        // If no events, use placeholder values
        $percentages = [20, 20, 20, 20, 20];
    }
    
    // Create color array matching the number of RCOs
    $chart_colors = array_slice($colors, 0, count($rcos));
    
    $conn->close();
    
    return [
        'rcos' => $rcos,
        'chart_data' => [
            'labels' => $labels,
            'data' => $data,
            'percentages' => $percentages,
            'colors' => $chart_colors
        ]
    ];
} 