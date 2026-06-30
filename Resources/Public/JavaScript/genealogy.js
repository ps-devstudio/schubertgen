document.documentElement.classList.add('schubertgen-js');

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-schubertgen-d3-tree]').forEach(initSchubertD3Tree);
  document.querySelectorAll('[data-schubertgen-tree]:not(.is-d3-ready)').forEach(initSchubertTree);
});

function initSchubertD3Tree(root) {
  const dataElement = root.querySelector('[data-schubertgen-chart-json]');
  const mount = root.querySelector('[data-schubertgen-d3-stage]');
  if (!dataElement || !mount || !dataElement.textContent.trim()) {
    return;
  }

  let chart;
  try {
    chart = JSON.parse(dataElement.textContent);
  } catch {
    return;
  }

  if (!chart || !Array.isArray(chart.generations) || chart.generations.length === 0) {
    return;
  }

  loadD3().then(() => {
    renderD3Tree(root, mount, chart);
    root.classList.add('is-d3-ready');
  }).catch(() => {
    initSchubertTree(root);
  });
}

function loadD3() {
  if (window.d3) {
    return Promise.resolve(window.d3);
  }

  return new Promise((resolve, reject) => {
    const existing = document.querySelector('script[data-schubertgen-d3]');
    if (existing) {
      existing.addEventListener('load', () => resolve(window.d3), { once: true });
      existing.addEventListener('error', reject, { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js';
    script.defer = true;
    script.dataset.schubertgenD3 = 'true';
    script.addEventListener('load', () => resolve(window.d3), { once: true });
    script.addEventListener('error', reject, { once: true });
    document.head.appendChild(script);
  });
}

function renderD3Tree(root, mount, chart) {
  const d3 = window.d3;
  const zoomInput = root.querySelector('[data-schubertgen-tree-zoom]');
  const zoomIn = root.querySelector('[data-schubertgen-tree-zoom-in]');
  const zoomOut = root.querySelector('[data-schubertgen-tree-zoom-out]');
  const fit = root.querySelector('[data-schubertgen-tree-fit]');
  const reset = root.querySelector('[data-schubertgen-tree-reset]');

  mount.innerHTML = '';

  const compact = window.matchMedia('(max-width: 760px)').matches;
  const personWidth = compact ? 260 : 330;
  const personHeight = compact ? 150 : 172;
  const coupleWidth = compact ? 540 : 690;
  const coupleHeight = compact ? 160 : 182;
  const generationGap = compact ? 230 : 285;
  const nodeGap = compact ? 34 : 58;
  const childGroupPad = compact ? 18 : 22;
  const childGroupLabelHeight = compact ? 30 : 34;
  const childGap = compact ? 24 : 34;
  const margin = { top: 42, right: 44, bottom: 52, left: 44 };

  const generations = chart.generations.map((generation, generationIndex) => {
    return {
      ...generation,
      generationIndex,
      nodes: (generation.nodes || []).map((node) => {
        if (node.type === 'childGroup') {
          const children = node.children || [];
          return {
            ...node,
            width: Math.max(
              personWidth,
              children.length * personWidth + Math.max(0, children.length - 1) * childGap + childGroupPad * 2
            ),
            height: personHeight + childGroupLabelHeight + childGroupPad * 2,
            generationIndex,
          };
        }

        const width = node.type === 'person' ? personWidth : coupleWidth;
        const height = node.type === 'person' ? personHeight : coupleHeight;
        return { ...node, width, height, generationIndex };
      }),
    };
  });

  const firstGeneration = generations[0];
  const secondGeneration = generations[1];
  if (firstGeneration?.type === 'childGroups' && secondGeneration?.type === 'couples') {
    const coupleByUid = new Map(secondGeneration.nodes.map((node) => [String(node.uid), node]));
    firstGeneration.nodes.forEach((node) => {
      const couple = coupleByUid.get(String(node.uid));
      if (couple) {
        const sharedWidth = Math.max(node.width, couple.width);
        node.columnWidth = sharedWidth;
        couple.columnWidth = sharedWidth;
      }
    });
  }

  const maxGenerationWidth = Math.max(...generations.map((generation) => {
    return generation.nodes.reduce((sum, node) => sum + (node.columnWidth || node.width), 0) + Math.max(0, generation.nodes.length - 1) * nodeGap;
  }));
  const width = Math.max(920, maxGenerationWidth + margin.left + margin.right);
  const height = margin.top + margin.bottom + generations.reduce((sum, generation) => {
    return sum + Math.max(...generation.nodes.map((node) => node.height), coupleHeight);
  }, 0) + Math.max(0, generations.length - 1) * generationGap;

  const nodeByPersonUid = new Map();
  const personOccurrenceByContext = new Map();
  const nodeByFamilyUid = new Map();
  const childGroupByFamilyUid = new Map();

  let y = margin.top;
  generations.forEach((generation) => {
    const generationWidth = generation.nodes.reduce((sum, node) => sum + (node.columnWidth || node.width), 0) + Math.max(0, generation.nodes.length - 1) * nodeGap;
    let x = margin.left + (width - margin.left - margin.right - generationWidth) / 2;

    generation.nodes.forEach((node) => {
      const columnWidth = node.columnWidth || node.width;
      node.x = x + (columnWidth - node.width) / 2;
      node.y = y;
      x += columnWidth + nodeGap;

      if (node.type === 'person') {
        nodeByPersonUid.set(String(node.uid), node);
      } else if (node.type === 'childGroup') {
        childGroupByFamilyUid.set(String(node.uid), node);
        (node.children || []).forEach((person, index) => {
          nodeByPersonUid.set(String(person.uid), {
            ...person,
            x: node.x + childGroupPad + index * (personWidth + childGap),
            y: node.y + childGroupLabelHeight + childGroupPad,
            width: personWidth,
            height: personHeight,
            childGroupNode: node,
          });
        });
      } else if (node.type === 'couple') {
        nodeByFamilyUid.set(String(node.uid), node);
        const parents = [node.husband, node.wife].filter(Boolean);
        parents.forEach((person, index) => {
          if (!person) {
            return;
          }
          const personNode = {
            ...person,
            x: parents.length === 1 ? node.x : node.x + (index === 0 ? 0 : node.width / 2),
            y: node.y,
            width: parents.length === 1 ? node.width : node.width / 2,
            height: node.height,
            familyNode: node,
          };
          nodeByPersonUid.set(String(person.uid), {
            ...personNode,
          });
          personOccurrenceByContext.set(`${person.uid}:${node.uid}`, personNode);
        });
      }
    });

    y += Math.max(...generation.nodes.map((node) => node.height), coupleHeight) + generationGap;
  });

  const links = [];
  generations.forEach((generation) => {
    generation.nodes.filter((node) => node.type === 'couple').forEach((node) => {
      const childGroup = childGroupByFamilyUid.get(String(node.uid)) || null;

      if (childGroup) {
        links.push({
          source: childGroup,
          target: node,
          grouped: true,
        });
        return;
      }

      if ((node.childLinks || []).length > 0) {
        node.childLinks.forEach((childLink) => {
          const child = personOccurrenceByContext.get(`${childLink.childUid}:${childLink.contextFamilyUid}`)
            || nodeByPersonUid.get(String(childLink.childUid));
          if (child) {
            links.push({
              source: child,
              target: node,
            });
          }
        });
        return;
      }

      (node.childUids || []).forEach((childUid) => {
        const child = nodeByPersonUid.get(String(childUid));
        if (child) {
          links.push({
            source: child,
            target: node,
          });
        }
      });
    });
  });

  const svg = d3.select(mount)
    .append('svg')
    .attr('class', 'schubertgen-d3')
    .attr('width', '100%')
    .attr('height', '100%')
    .attr('role', 'img');

  const defs = svg.append('defs');
  defs.append('filter')
    .attr('id', 'schubertgen-card-shadow')
    .attr('x', '-20%')
    .attr('y', '-20%')
    .attr('width', '140%')
    .attr('height', '140%')
    .append('feDropShadow')
    .attr('dx', 0)
    .attr('dy', 10)
    .attr('stdDeviation', 10)
    .attr('flood-color', '#1f2937')
    .attr('flood-opacity', 0.12);

  const canvas = svg.append('g').attr('class', 'schubertgen-d3__canvas');
  const linkLayer = canvas.append('g').attr('class', 'schubertgen-d3__links');
  const nodeLayer = canvas.append('g').attr('class', 'schubertgen-d3__nodes');

  linkLayer.selectAll('path')
    .data(links)
    .join('path')
    .attr('class', 'schubertgen-d3__link')
    .attr('d', (link) => elbowPath(link.source, link.target));

  generations.forEach((generation) => {
    canvas.append('text')
      .attr('class', 'schubertgen-d3__generation-label')
      .attr('x', 18)
      .attr('y', generation.nodes[0]?.y + 18 || margin.top)
      .text(generation.label || '');
  });

  const nodes = nodeLayer.selectAll('g.schubertgen-d3__node')
    .data(generations.flatMap((generation) => generation.nodes))
    .join('g')
    .attr('class', (node) => `schubertgen-d3__node schubertgen-d3__node--${node.type}`)
    .attr('transform', (node) => `translate(${node.x},${node.y})`);

  nodes.filter((node) => node.type === 'person').each(function renderPerson(node) {
    drawPerson(d3.select(this), node, 0, 0, node.width, node.height);
  });

  nodes.filter((node) => node.type === 'childGroup').each(function renderChildGroup(node) {
    const group = d3.select(this);
    group.append('rect')
      .attr('class', 'schubertgen-d3__child-group-frame')
      .attr('x', 0)
      .attr('y', 0)
      .attr('width', node.width)
      .attr('height', node.height)
      .attr('rx', 10);

    group.append('text')
      .attr('class', 'schubertgen-d3__child-group-label')
      .attr('x', childGroupPad)
      .attr('y', 20)
      .text(node.label || '');

    (node.children || []).forEach((person, index) => {
      drawPerson(
        group,
        person,
        childGroupPad + index * (personWidth + childGap),
        childGroupLabelHeight + childGroupPad,
        personWidth,
        personHeight
      );
    });
  });

  nodes.filter((node) => node.type === 'couple').each(function renderCouple(node) {
    const group = d3.select(this);
    const halfWidth = node.width / 2;
    const parents = [node.husband, node.wife].filter(Boolean);

    group.append('rect')
      .attr('class', 'schubertgen-d3__couple-frame')
      .attr('x', -10)
      .attr('y', -10)
      .attr('width', node.width + 20)
      .attr('height', node.height + 20)
      .attr('rx', 8);

    if (parents.length === 1) {
      drawPerson(group, parents[0], 0, 0, node.width, node.height);
    } else {
      if (node.husband) {
        drawPerson(group, node.husband, 0, 0, halfWidth - 5, node.height);
      }
      if (node.wife) {
        drawPerson(group, node.wife, halfWidth + 5, 0, halfWidth - 5, node.height);
      }
    }
    if (node.label) {
      group.append('text')
        .attr('class', 'schubertgen-d3__marriage')
        .attr('x', node.width / 2)
        .attr('y', node.height + 26)
        .attr('text-anchor', 'middle')
        .text(node.label);
    }
  });

  const zoom = d3.zoom()
    .scaleExtent([0.35, 3.2])
    .on('zoom', (event) => {
      canvas.attr('transform', event.transform);
      if (zoomInput) {
        zoomInput.value = String(Math.round(event.transform.k * 100));
      }
    });

  svg.call(zoom);

  const setZoom = (scale) => {
    const nextScale = Math.max(0.35, Math.min(3.2, scale));
    svg.transition().duration(180).call(zoom.scaleTo, nextScale);
  };

  const fitToScreen = () => {
    const bounds = canvas.node().getBBox();
    const outer = mount.getBoundingClientRect();
    const scale = Math.min(1.15, Math.max(0.35, Math.min(outer.width / (bounds.width + 80), outer.height / (bounds.height + 80))));
    const x = (outer.width - bounds.width * scale) / 2 - bounds.x * scale;
    const y = 28 - bounds.y * scale;
    svg.transition().duration(220).call(zoom.transform, d3.zoomIdentity.translate(x, y).scale(scale));
  };

  const focusPerson = () => {
    const focusUid = chart.focusPerson?.uid;
    const focusNode = focusUid ? nodeByPersonUid.get(String(focusUid)) : null;
    const outer = mount.getBoundingClientRect();
    const scale = compact ? 1.35 : 1.45;
    const x = outer.width / 2 - (focusNode ? focusNode.x + focusNode.width / 2 : width / 2) * scale;
    const y = 54 - (focusNode ? focusNode.y : margin.top) * scale;
    svg.transition().duration(220).call(zoom.transform, d3.zoomIdentity.translate(x, y).scale(scale));
  };

  zoomInput?.addEventListener('input', () => setZoom(Number(zoomInput.value) / 100));
  zoomIn?.addEventListener('click', () => setZoom(Number(zoomInput?.value || 100) / 100 + 0.2));
  zoomOut?.addEventListener('click', () => setZoom(Number(zoomInput?.value || 100) / 100 - 0.2));
  fit?.addEventListener('click', fitToScreen);
  reset?.addEventListener('click', focusPerson);

  window.requestAnimationFrame(focusPerson);
}

function drawPerson(group, person, x, y, width, height) {
  const cardClass = `schubertgen-d3__card schubertgen-d3__card--${person.gender || 'unknown'}${person.focus ? ' is-focus' : ''}`;
  group.append('rect')
    .attr('class', cardClass)
    .attr('x', x)
    .attr('y', y)
    .attr('width', width)
    .attr('height', height)
    .attr('rx', 8);

  if (person.image) {
    group.append('image')
      .attr('class', 'schubertgen-d3__portrait')
      .attr('href', person.image)
      .attr('x', x + 14)
      .attr('y', y + 16)
      .attr('width', 52)
      .attr('height', 52)
      .attr('preserveAspectRatio', 'xMidYMid slice');
  }

  const titleX = x + (person.image ? 78 : 18);
  const titleWidth = Math.max(120, width - (person.image ? 94 : 36));
  wrapText(group, person.name || '', titleX, y + 28, titleWidth, 'schubertgen-d3__name', 2);

  const details = Array.isArray(person.details) && person.details.length > 0
    ? person.details
    : fallbackDetails(person);
  const detailLabelX = x + (person.image ? 78 : 16);
  const detailValueX = detailLabelX + 58;
  const detailWidth = Math.max(90, width - (detailValueX - x) - 16);
  details.slice(0, 4).forEach((detail, index) => {
    const rowY = y + 70 + index * 24;
    group.append('text')
      .attr('class', 'schubertgen-d3__detail-label')
      .attr('x', detailLabelX)
      .attr('y', rowY)
      .text(detail.label);
    wrapText(group, detail.value || '', detailValueX, rowY, detailWidth, 'schubertgen-d3__detail-value', 2, 11);
  });
}

function fallbackDetails(person) {
  return [
    person.birthDate ? { label: 'Geburt', value: [person.birthDate, person.birthPlace].filter(Boolean).join('\n') } : null,
    person.deathDate ? { label: 'Tod', value: [person.deathDate, person.deathPlace].filter(Boolean).join('\n') } : null,
  ].filter(Boolean);
}

function wrapText(group, text, x, y, width, className, maxLines, lineHeight = 15) {
  const approxChars = Math.max(12, Math.floor(width / 7.2));
  const words = String(text).replace(/\n/g, ' / ').split(/\s+/).filter(Boolean);
  const lines = [];
  let current = '';

  words.forEach((word) => {
    const next = current ? `${current} ${word}` : word;
    if (next.length > approxChars && current) {
      lines.push(current);
      current = word;
    } else {
      current = next;
    }
  });
  if (current) {
    lines.push(current);
  }

  lines.slice(0, maxLines).forEach((line, index) => {
    group.append('text')
      .attr('class', className)
      .attr('x', x)
      .attr('y', y + index * lineHeight)
      .text(index === maxLines - 1 && lines.length > maxLines ? `${line.replace(/\s+\S+$/, '')} ...` : line);
  });
}

function elbowPath(source, target) {
  const sourceX = source.x + source.width / 2;
  const sourceY = source.y + source.height + 8;
  const targetX = target.x + target.width / 2;
  const targetY = target.y - 14;
  const middleY = sourceY + (targetY - sourceY) / 2;
  return `M${sourceX},${sourceY} V${middleY} H${targetX} V${targetY}`;
}

function initSchubertTree(root) {
  const viewport = root.querySelector('[data-schubertgen-tree-viewport]');
  const stage = root.querySelector('[data-schubertgen-tree-stage]');
  const zoomInput = root.querySelector('[data-schubertgen-tree-zoom]');
  const zoomIn = root.querySelector('[data-schubertgen-tree-zoom-in]');
  const zoomOut = root.querySelector('[data-schubertgen-tree-zoom-out]');
  const fit = root.querySelector('[data-schubertgen-tree-fit]');
  const reset = root.querySelector('[data-schubertgen-tree-reset]');
  if (!viewport || !stage || !zoomInput) {
    return;
  }

  let scale = Number(zoomInput.value) / 100;
  let isDragging = false;
  let dragStartX = 0;
  let dragStartY = 0;
  let scrollStartX = 0;
  let scrollStartY = 0;
  const naturalWidth = stage.scrollWidth;
  const naturalHeight = stage.scrollHeight;

  const setScale = (nextScale, keepCenter = true) => {
    const oldScale = scale;
    scale = Math.min(3.2, Math.max(0.35, nextScale));
    zoomInput.value = String(Math.round(scale * 100));
    root.style.setProperty('--sg-tree-scale', String(scale));

    stage.style.width = `${naturalWidth * scale}px`;
    stage.style.height = `${naturalHeight * scale}px`;

    if (keepCenter && oldScale > 0) {
      const ratio = scale / oldScale;
      viewport.scrollLeft = (viewport.scrollLeft + viewport.clientWidth / 2) * ratio - viewport.clientWidth / 2;
      viewport.scrollTop = (viewport.scrollTop + viewport.clientHeight / 2) * ratio - viewport.clientHeight / 2;
    }
  };

  const fitToViewport = () => {
    const available = viewport.clientWidth - 24;
    setScale(Math.min(1, Math.max(0.35, available / (naturalWidth || 1))), false);
    viewport.scrollTo({ left: 0, top: 0, behavior: 'smooth' });
  };

  zoomInput.addEventListener('input', () => setScale(Number(zoomInput.value) / 100));
  zoomIn?.addEventListener('click', () => setScale(scale + 0.2));
  zoomOut?.addEventListener('click', () => setScale(scale - 0.2));
  fit?.addEventListener('click', fitToViewport);
  reset?.addEventListener('click', () => {
    setScale(1, false);
    viewport.scrollTo({ left: 0, top: 0, behavior: 'smooth' });
  });

  viewport.addEventListener('pointerdown', (event) => {
    if (event.target.closest('a, button, input')) {
      return;
    }
    isDragging = true;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    scrollStartX = viewport.scrollLeft;
    scrollStartY = viewport.scrollTop;
    viewport.classList.add('is-dragging');
    viewport.setPointerCapture(event.pointerId);
  });

  viewport.addEventListener('pointermove', (event) => {
    if (!isDragging) {
      return;
    }
    viewport.scrollLeft = scrollStartX - (event.clientX - dragStartX);
    viewport.scrollTop = scrollStartY - (event.clientY - dragStartY);
  });

  const stopDragging = () => {
    isDragging = false;
    viewport.classList.remove('is-dragging');
  };

  viewport.addEventListener('pointerup', stopDragging);
  viewport.addEventListener('pointercancel', stopDragging);
  viewport.addEventListener('mouseleave', stopDragging);

  viewport.addEventListener('wheel', (event) => {
    if (!event.ctrlKey && !event.metaKey) {
      return;
    }
    event.preventDefault();
    setScale(scale + (event.deltaY < 0 ? 0.05 : -0.05));
  }, { passive: false });

  setScale(scale, false);
  if (window.matchMedia('(max-width: 700px)').matches) {
    window.requestAnimationFrame(fitToViewport);
  }
}
