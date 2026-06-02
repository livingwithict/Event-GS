export default function EventContextBar({ event, currentDay, totalDays, isMultiDay, eventDays, onSelectDay }) {
  if (!event) return null;

  return (
    <div style={styles.container}>
      <div style={styles.header}>
        <span style={styles.eventName}>{event.event_name}</span>
        {isMultiDay && (
          <span style={styles.dayBadge}>Day {currentDay} of {totalDays}</span>
        )}
      </div>
      {isMultiDay && eventDays.length > 1 && (
        <div style={styles.daySelector}>
          {eventDays.map((day) => (
            <button
              key={day.day_number}
              onClick={() => onSelectDay(day.day_number)}
              style={{
                ...styles.dayBtn,
                background: day.day_number === currentDay ? '#1a56db' : '#e5e7eb',
                color: day.day_number === currentDay ? 'white' : '#374151',
              }}
            >
              D{day.day_number}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

const styles = {
  container: {
    background: '#f0f4ff',
    border: '1px solid #c7d2fe',
    borderRadius: 8,
    padding: 10,
    marginBottom: 12,
  },
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  eventName: {
    fontWeight: 600,
    fontSize: 14,
    color: '#1e293b',
  },
  dayBadge: {
    background: '#1a56db',
    color: 'white',
    padding: '2px 10px',
    borderRadius: 12,
    fontSize: 12,
    fontWeight: 600,
  },
  daySelector: {
    display: 'flex',
    gap: 6,
    marginTop: 8,
  },
  dayBtn: {
    padding: '4px 12px',
    border: 'none',
    borderRadius: 4,
    fontSize: 12,
    fontWeight: 600,
    cursor: 'pointer',
  },
};
