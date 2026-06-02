import { useState, useEffect } from 'react';
import api from '../utils/api';

const ACTION_COLORS = {
  CHECKIN: '#1a56db',
  LUNCH: '#16a34a',
  DINNER: '#d97706',
  CARD_DELIVERY: '#7c3aed',
};

export default function ActionButtons({ guest, onUpdate, day }) {
  const [loading, setLoading] = useState(null);
  const [actions, setActions] = useState([]);

  useEffect(() => {
    if (!guest?.id) return;

    const dayParam = day ? `?day=${day}` : '';
    api.get(`/event/${guest.event_id || guest.unique_code}/actions${dayParam}`).then(({ data }) => {
      setActions(data.data || []);
    }).catch(() => {
      setActions([]);
    });
  }, [guest?.id]);

  const handleAction = async (action) => {
    setLoading(action.action_code);
    try {
      const res = await api.post('/scan-action', {
        registration_id: guest.id,
        action_type_id: action.id,
      });
      alert(res.data.message);
      onUpdate();
    } catch (err) {
      alert(err.response?.data?.message || 'Action failed');
    } finally {
      setLoading(null);
    }
  };

  const getActionStatus = (actionCode) => {
    const action = guest.actions?.find((a) => a.action_code === actionCode);
    return action?.completed ?? false;
  };

  if (actions.length === 0) {
    return (
      <div style={styles.container}>
        <p style={{ color: '#6b7280', fontSize: 14, textAlign: 'center' }}>No actions configured for this event.</p>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      {actions.map((action) => {
        const completed = getActionStatus(action.action_code);
        const color = ACTION_COLORS[action.action_code] || '#2563eb';

        return (
          <button
            key={action.id}
            onClick={() => handleAction(action)}
            disabled={completed || loading === action.action_code}
            style={{
              ...styles.btn,
              background: completed ? '#9ca3af' : color,
            }}
          >
            {completed ? `${action.action_name} Done` : action.action_name}
          </button>
        );
      })}
    </div>
  );
}

const styles = {
  container: {
    display: 'flex',
    gap: 8,
    flexWrap: 'wrap',
    marginBottom: 16,
  },
  btn: {
    flex: '1 1 auto',
    minWidth: 100,
    padding: 12,
    fontSize: 14,
    color: 'white',
    border: 'none',
    borderRadius: 6,
    cursor: 'pointer',
  },
};
