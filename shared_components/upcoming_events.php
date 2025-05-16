<?php
// Get upcoming events from database
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5");
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="events-container">
    <div class="events-title">UPCOMING EVENTS</div>
    <div class="events-list">
        <?php if (empty($events)): ?>
            <div class="no-events">No upcoming events scheduled.</div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <div class="event-card">
                    <div class="event-date">
                        <?= date('M d, Y', strtotime($event['event_date'])) ?>
                    </div>
                    <div class="event-details">
                        <h3><?= htmlspecialchars($event['title']) ?></h3>
                        <p><?= htmlspecialchars($event['description']) ?></p>
                        <div class="event-meta">
                            <span class="event-time">
                                <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($event['event_time'])) ?>
                            </span>
                            <span class="event-location">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.events-container {
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
}

.events-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 20px;
    color: #333;
}

.events-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.event-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 16px;
    display: flex;
    gap: 16px;
    transition: transform 0.2s;
}

.event-card:hover {
    transform: translateY(-2px);
}

.event-date {
    background: #ff9800;
    color: white;
    padding: 12px;
    border-radius: 6px;
    text-align: center;
    min-width: 100px;
    font-weight: 600;
}

.event-details {
    flex: 1;
}

.event-details h3 {
    margin: 0 0 8px 0;
    color: #333;
}

.event-details p {
    margin: 0 0 12px 0;
    color: #666;
    line-height: 1.4;
}

.event-meta {
    display: flex;
    gap: 16px;
    color: #888;
    font-size: 0.9rem;
}

.event-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.no-events {
    text-align: center;
    color: #666;
    padding: 32px;
    background: #f5f5f5;
    border-radius: 8px;
}
</style> 