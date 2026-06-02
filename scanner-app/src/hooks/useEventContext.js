import { useState, useEffect } from 'react';
import api from '../utils/api';

export function useEventContext(eventId) {
  const [event, setEvent] = useState(null);
  const [currentDay, setCurrentDay] = useState(null);
  const [totalDays, setTotalDays] = useState(1);
  const [isMultiDay, setIsMultiDay] = useState(false);
  const [eventDays, setEventDays] = useState([]);
  const [loading, setLoading] = useState(false);

  const savedEventId = eventId || localStorage.getItem('scanner_event_id');
  const savedDay = localStorage.getItem('scanner_event_day');

  useEffect(() => {
    if (!savedEventId) return;

    setLoading(true);
    api.get(`/event/${savedEventId}/info`)
      .then(({ data }) => {
        const d = data.data;
        setEvent(d);
        setIsMultiDay(d.is_multi_day);
        setTotalDays(d.total_days);
        setEventDays(d.event_days || []);

        const day = savedDay ? parseInt(savedDay) : (d.current_day || 1);
        setCurrentDay(day);
      })
      .catch(() => {
        setEvent(null);
      })
      .finally(() => setLoading(false));
  }, [savedEventId]);

  const selectDay = (day) => {
    setCurrentDay(day);
    localStorage.setItem('scanner_event_day', day);
  };

  const selectEvent = (id) => {
    localStorage.setItem('scanner_event_id', id);
    window.location.reload();
  };

  return {
    event,
    currentDay,
    totalDays,
    isMultiDay,
    eventDays,
    loading,
    selectDay,
    selectEvent,
    eventId: savedEventId ? parseInt(savedEventId) : null,
  };
}
