export default function GuestCard({ guest }) {
  const statusColor = (bool) => (bool ? '#16a34a' : '#9ca3af');

  return (
    <div style={styles.card}>
      {guest.guest_number && (
        <div style={styles.guestNumber}>{guest.guest_number}</div>
      )}
      <h2 style={{ margin: '0 0 8px' }}>{guest.name}</h2>
      {guest.category && (
        <span style={{ ...styles.badge, background: guest.category_color || '#6B7280', marginBottom: 8, display: 'inline-block' }}>
          {guest.category}
        </span>
      )}
      {guest.organization && <p style={{ margin: '0 0 4px', color: '#666' }}>{guest.organization}</p>}
      {guest.designation && <p style={{ margin: '0 0 8px', color: '#666' }}>{guest.designation}</p>}
      <div style={styles.statusRow}>
        {guest.actions && guest.actions.length > 0 ? guest.actions.map((action) => (
          <span key={action.action_code} style={{ ...styles.badge, background: statusColor(action.completed) }}>
            {action.action_name}: {action.completed ? 'Done' : 'Pending'}
          </span>
        )) : (
          <>
            <span style={{ ...styles.badge, background: statusColor(guest.has_entered) }}>
              Entry: {guest.has_entered ? 'Done' : 'Pending'}
            </span>
            <span style={{ ...styles.badge, background: statusColor(guest.lunch_used) }}>
              Lunch: {guest.lunch_used ? 'Used' : 'Available'}
            </span>
            <span style={{ ...styles.badge, background: statusColor(guest.dinner_used) }}>
              Dinner: {guest.dinner_used ? 'Used' : 'Available'}
            </span>
          </>
        )}
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
  guestNumber: {
    fontFamily: 'monospace',
    fontSize: 14,
    fontWeight: 700,
    color: '#1a56db',
    marginBottom: 8,
    padding: '4px 8px',
    background: '#eff6ff',
    borderRadius: 4,
    display: 'inline-block',
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
