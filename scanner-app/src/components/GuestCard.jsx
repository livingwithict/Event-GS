export default function GuestCard({ guest }) {
  const statusColor = (bool) => (bool ? '#16a34a' : '#9ca3af');

  return (
    <div style={styles.card}>
      <h2 style={{ margin: '0 0 8px' }}>{guest.name}</h2>
      {guest.organization && <p style={{ margin: '0 0 4px', color: '#666' }}>{guest.organization}</p>}
      {guest.designation && <p style={{ margin: '0 0 8px', color: '#666' }}>{guest.designation}</p>}
      <div style={styles.statusRow}>
        <span style={{ ...styles.badge, background: statusColor(guest.has_entered) }}>
          Entry: {guest.has_entered ? 'Done' : 'Pending'}
        </span>
        <span style={{ ...styles.badge, background: statusColor(guest.lunch_used) }}>
          Lunch: {guest.lunch_used ? 'Used' : 'Available'}
        </span>
        <span style={{ ...styles.badge, background: statusColor(guest.dinner_used) }}>
          Dinner: {guest.dinner_used ? 'Used' : 'Available'}
        </span>
      </div>
    </div>
  );
}

const styles = {
  card: {
    background: 'white',
    border: '1px solid #e5e7eb',
    borderRadius: 8,
    padding: 16,
    marginBottom: 12,
  },
  statusRow: {
    display: 'flex',
    gap: 8,
    flexWrap: 'wrap',
    marginTop: 8,
  },
  badge: {
    padding: '4px 8px',
    borderRadius: 4,
    color: 'white',
    fontSize: 12,
  },
};
