import { useState } from 'react';
import api from '../utils/api';

export default function ActionButtons({ guest, onUpdate }) {
  const [loading, setLoading] = useState(null);

  const handleAction = async (action, payload = {}) => {
    setLoading(action);
    try {
      const endpoint = action === 'entry' ? '/entry' : '/meal';
      const data = action === 'entry'
        ? { registration_id: guest.id }
        : { registration_id: guest.id, meal_type: payload.meal_type };

      const res = await api.post(endpoint, data);
      alert(res.data.message);
      onUpdate();
    } catch (err) {
      const msg = err.response?.data?.message || 'Action failed';
      alert(msg);
    } finally {
      setLoading(null);
    }
  };

  return (
    <div style={styles.container}>
      <button
        onClick={() => handleAction('entry')}
        disabled={guest.has_entered || loading === 'entry'}
        style={{
          ...styles.btn,
          background: guest.has_entered ? '#9ca3af' : '#1a56db',
        }}
      >
        {guest.has_entered ? 'Already Entered' : 'Record Entry'}
      </button>

      <button
        onClick={() => handleAction('meal', { meal_type: 'lunch' })}
        disabled={guest.lunch_used || loading === 'lunch'}
        style={{
          ...styles.btn,
          background: guest.lunch_used ? '#9ca3af' : '#16a34a',
        }}
      >
        {guest.lunch_used ? 'Lunch Used' : 'Mark Lunch'}
      </button>

      <button
        onClick={() => handleAction('meal', { meal_type: 'dinner' })}
        disabled={guest.dinner_used || loading === 'dinner'}
        style={{
          ...styles.btn,
          background: guest.dinner_used ? '#9ca3af' : '#d97706',
        }}
      >
        {guest.dinner_used ? 'Dinner Used' : 'Mark Dinner'}
      </button>
    </div>
  );
}

const styles = {
  container: {
    display: 'flex',
    gap: 8,
    marginBottom: 16,
  },
  btn: {
    flex: 1,
    padding: 12,
    fontSize: 14,
    color: 'white',
    border: 'none',
    borderRadius: 6,
    cursor: 'pointer',
  },
};
