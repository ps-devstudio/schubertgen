document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-schubertgen-map]').forEach(initSchubertMap);
});

function initSchubertMap(mapElement) {
  if (!window.L) {
    return;
  }

  const root = mapElement.closest('.schubertgen-map');
  const dataElement = root?.querySelector('[data-schubertgen-map-events]');
  const listElement = root?.querySelector('[data-schubertgen-map-list]');
  const searchElement = root?.querySelector('[data-schubertgen-map-search]');
  if (!dataElement || !dataElement.textContent.trim()) {
    return;
  }

  let events = [];
  try {
    events = JSON.parse(dataElement.textContent);
  } catch {
    return;
  }

  events = events
    .filter((event) => Number(event.latitude) || Number(event.longitude))
    .sort((first, second) => compareEventEntries({ event: first }, { event: second }));
  const map = L.map(mapElement, {
    scrollWheelZoom: true,
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap',
  }).addTo(map);

  if (events.length === 0) {
    map.setView([48.209167, 16.371667], 5);
    if (listElement) {
      listElement.innerHTML = '<p class="schubertgen__empty">Keine Ereignisse mit Koordinaten gefunden.</p>';
    }
    return;
  }

  const eventsByPlace = new Map();
  events.forEach((event) => {
    const key = `${Number(event.latitude).toFixed(6)},${Number(event.longitude).toFixed(6)}`;
    if (!eventsByPlace.has(key)) {
      eventsByPlace.set(key, []);
    }
    eventsByPlace.get(key).push(event);
  });

  const bounds = [];
  const markers = [];
  eventsByPlace.forEach((placeEvents) => {
    const first = placeEvents[0];
    const latlng = [Number(first.latitude), Number(first.longitude)];
    bounds.push(latlng);

    const marker = L.circleMarker(latlng, {
      radius: Math.min(18, 7 + Math.sqrt(placeEvents.length) * 2.5),
      color: '#2f6fa9',
      fillColor: eventColor(first.type),
      fillOpacity: 0.82,
      weight: 2,
    }).addTo(map);
    marker.bindPopup(popupHtml(placeEvents), {
      autoPan: false,
      className: 'schubertgen-map__leaflet-popup',
      maxHeight: 280,
      maxWidth: 380,
    });
    markers.push({ marker, events: placeEvents });
  });

  map.setView([48.209167, 16.371667], 6);

  if (listElement) {
    renderEventList(listElement, markers, searchElement);
  }
}

function renderEventList(listElement, markers, searchElement) {
  const entries = markers
    .flatMap((item) => item.events.map((event) => ({ event, marker: item.marker })))
    .sort(compareEventEntries);

  const draw = () => {
    const query = normalizeSearch(searchElement?.value || '');
    const visibleEntries = query === ''
      ? entries
      : entries.filter(({ event }) => eventMatchesSearch(event, query));

    listElement.innerHTML = '';
    if (visibleEntries.length === 0) {
      listElement.innerHTML = '<p class="schubertgen__empty">Keine passenden Ereignisse gefunden.</p>';
      return;
    }

    visibleEntries.forEach(({ event, marker }) => {
      const button = document.createElement('button');
      button.className = 'schubertgen-map__event';
      button.type = 'button';
      button.innerHTML = `
        <strong>${escapeHtml(event.title || event.place || 'Ereignis')}</strong>
        <span>${escapeHtml([event.typeLabel, event.date].filter(Boolean).join(' · '))}</span>
        <small>${escapeHtml(event.place || '')}</small>
      `;
      button.addEventListener('click', () => {
        marker.openPopup();
        marker._map?.setView(marker.getLatLng(), Math.max(marker._map.getZoom(), 8), { animate: true });
      });
      listElement.appendChild(button);
    });
  };

  searchElement?.addEventListener('input', draw);
  draw();
}

function compareEventEntries(first, second) {
  return compareSortDate(first.event.sortDate, second.event.sortDate)
    || String(first.event.place || '').localeCompare(String(second.event.place || ''), 'de', { sensitivity: 'base' })
    || String(first.event.title || '').localeCompare(String(second.event.title || ''), 'de', { sensitivity: 'base' })
    || String(first.event.typeLabel || '').localeCompare(String(second.event.typeLabel || ''), 'de', { sensitivity: 'base' });
}

function compareSortDate(first, second) {
  const firstDate = Number(first) || Number.MAX_SAFE_INTEGER;
  const secondDate = Number(second) || Number.MAX_SAFE_INTEGER;
  return firstDate - secondDate;
}

function eventMatchesSearch(event, query) {
  return normalizeSearch([
    event.title,
    event.typeLabel,
    event.date,
    event.place,
  ].filter(Boolean).join(' ')).includes(query);
}

function normalizeSearch(value) {
  return String(value)
    .toLocaleLowerCase('de')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function popupHtml(events) {
  const place = events[0]?.place || '';
  const items = events.slice(0, 18).map((event) => {
    return `<li><strong>${escapeHtml(event.title || '')}</strong><br><span>${escapeHtml([event.typeLabel, event.date].filter(Boolean).join(' · '))}</span></li>`;
  }).join('');
  const more = events.length > 18 ? `<p>${events.length - 18} weitere Ereignisse</p>` : '';

  return `<div class="schubertgen-map__popup"><h3>${escapeHtml(place)}</h3><ul>${items}</ul>${more}</div>`;
}

function eventColor(type) {
  return {
    birth: '#7bb7ea',
    christening: '#a7d8ff',
    marriage: '#e9c46a',
    death: '#e87b7b',
    burial: '#b58bd9',
  }[type] || '#94a3b8';
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
