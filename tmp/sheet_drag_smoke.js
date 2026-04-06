const { Window } = require('/tmp/happy-dom-smoke/node_modules/happy-dom');

const window = new Window();
const document = window.document;
global.window = window;
global.document = document;

document.body.innerHTML = `
  <div id="sheet-rates" class="apple-sheet-overlay active">
    <div class="apple-sheet">
      <div class="apple-sheet-handle"></div>
    </div>
  </div>
`;

function closeSheetOverlayByElement(overlay) {
  if (!overlay) {
    return;
  }

  overlay.classList.remove('active');
  if (!document.querySelector('.apple-sheet-overlay.active')) {
    document.body.style.overflow = '';
  }
}

function bindSheetHandleDragClose(sheetId) {
  var overlay = document.getElementById(sheetId);
  if (!overlay) {
    return;
  }

  var handle = overlay.querySelector('.apple-sheet-handle');
  var sheet = overlay.querySelector('.apple-sheet');
  if (!handle || !sheet || handle.dataset.dragCloseFallbackBound === '1') {
    return;
  }

  handle.dataset.dragCloseFallbackBound = '1';
  handle.style.touchAction = 'none';
  handle.style.cursor = 'ns-resize';

  var startY = 0;
  var deltaY = 0;
  var dragging = false;

  function getCloseThreshold() {
    var height = sheet.getBoundingClientRect().height || sheet.offsetHeight || 0;
    return Math.max(72, Math.round(height * 0.25));
  }

  function beginDrag(clientY) {
    if (!overlay.classList.contains('active')) {
      return false;
    }

    startY = clientY;
    deltaY = 0;
    dragging = true;
    sheet.style.transition = 'none';
    sheet.style.willChange = 'transform';
    return true;
  }

  function updateDrag(clientY) {
    if (!dragging) {
      return;
    }

    deltaY = Math.max(0, clientY - startY);
    sheet.style.transform = 'translateY(' + deltaY + 'px)';
  }

  function finishDrag() {
    if (!dragging) {
      return;
    }

    var shouldClose = deltaY >= getCloseThreshold();
    dragging = false;
    sheet.style.willChange = '';
    sheet.style.transition = 'transform 0.22s cubic-bezier(0.32, 0.72, 0, 1)';
    sheet.style.transform = '';

    if (shouldClose) {
      closeSheetOverlayByElement(overlay);
    }
  }

  var onMouseMove = function(event) {
    updateDrag(event.clientY);
  };
  var onMouseUp = function() {
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('mouseup', onMouseUp);
    finishDrag();
  };

  handle.addEventListener('mousedown', function(event) {
    if (event.button !== 0) {
      return;
    }
    if (!beginDrag(event.clientY)) {
      return;
    }

    event.preventDefault();
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
  });
}

bindSheetHandleDragClose('sheet-rates');

const overlay = document.getElementById('sheet-rates');
const handle = overlay.querySelector('.apple-sheet-handle');

handle.dispatchEvent(new window.MouseEvent('mousedown', { bubbles: true, cancelable: true, button: 0, clientY: 100 }));
document.dispatchEvent(new window.MouseEvent('mousemove', { bubbles: true, cancelable: true, clientY: 240 }));
document.dispatchEvent(new window.MouseEvent('mouseup', { bubbles: true, cancelable: true, clientY: 240 }));

const pass = !overlay.classList.contains('active');
console.log(pass ? 'PASS: handle drag closes sheet' : 'FAIL: sheet still active');
process.exit(pass ? 0 : 1);
