// This script assumes required global HTML elements exist (pipViewer, pipContent, etc.)
// See main page HTML for expected structure

// Initialize PDF.js
if (typeof pdfjsLib !== "undefined") {
  pdfjsLib.GlobalWorkerOptions.workerSrc = "js/pdf.worker.min.js";
}

let currentType = "",
  currentPath = "",
  currentScale = 1,
  currentPdf = null,
  currentPage = 1;

function openPiPViewer(path, type) {
  currentType = type;
  currentPath = path;
  currentScale = 1;
  currentPage = 1;

  const pipViewer = document.getElementById("pipViewer");
  const pipContent = document.getElementById("pipContent");
  const btnMin = document.getElementById("pipMinimize");
  const btnMax = document.getElementById("pipMaximize");
  const pipBackdrop = document.getElementById("pipBackdrop");

  pipViewer.className = "pip-viewer";
  pipViewer.classList.add("maximize-mode");
  pipViewer.style.transform = "translate(-50%, -50%)";
  if (type === "pdf") pipViewer.classList.add("pdf-mode");

  pipViewer.style = "";
  pipContent.innerHTML = "";
  btnMin.classList.remove("d-none");
  btnMax.classList.add("d-none");
  document.body.classList.add("no-scroll");
  pipBackdrop.style.display = "block";
  pipContent.style.position = "relative";

  if (type === "image") {
    const img = document.createElement("img");
    img.id = "pipImage";
    img.src = path;
    img.style.position = "absolute";
    img.style.left = "50%";
    img.style.top = "50%";
    img.style.transformOrigin = "center center";
    img.style.transform = "translate(-50%, -50%) scale(1)";
    img.style.maxWidth = "100%";
    img.style.maxHeight = "100%";

    pipContent.innerHTML = "";
    pipContent.appendChild(img);
    setupPanZoom(img);
  } else if (type === "pdf") {
    pdfjsLib.getDocument(path).promise.then(function (pdf) {
      currentPdf = pdf;
      currentPage = 1;
      showPdfPage(currentPage);

      // Keyboard navigation
      document.addEventListener("keydown", function (e) {
        if (e.key === "ArrowUp" && currentPage > 1) {
          currentPage--;
          showPdfPage(currentPage);
        } else if (e.key === "ArrowDown" && currentPage < pdf.numPages) {
          currentPage++;
          showPdfPage(currentPage);
        }
      });

      // Simple touch handling
      let startY;

      pipContent.ontouchstart = function (e) {
        startY = e.touches[0].pageY;
      };

      pipContent.ontouchend = function (e) {
        let endY = e.changedTouches[0].pageY;
        let diff = startY - endY;

        if (Math.abs(diff) > 50) {
          // Min 50px swipe
          if (diff > 0 && currentPage < pdf.numPages) {
            // Swipe up
            currentPage++;
            showPdfPage(currentPage);
          } else if (diff < 0 && currentPage > 1) {
            // Swipe down
            currentPage--;
            showPdfPage(currentPage);
          }
        }
      };

      // Mouse wheel
      pipContent.onwheel = function (e) {
        if (e.deltaY > 0 && currentPage < pdf.numPages) {
          currentPage++;
          showPdfPage(currentPage);
        } else if (e.deltaY < 0 && currentPage > 1) {
          currentPage--;
          showPdfPage(currentPage);
        }
        e.preventDefault();
      };

      // Mouse click up/down detection (desktop only)
      pipContent.onmouseup = function (e) {
        // Skip if event originated from touch
        if (e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents) {
          return;
        }

        const rect = pipContent.getBoundingClientRect();
        const y = e.clientY - rect.top;

        if (y < rect.height / 2 && currentPage > 1) {
          currentPage--;
          showPdfPage(currentPage);
        } else if (y >= rect.height / 2 && currentPage < pdf.numPages) {
          currentPage++;
          showPdfPage(currentPage);
        }
      };

      // Hammer.js swipe up/down (tablet)
      const swipeHammer = new Hammer(pipContent);
      swipeHammer.get("swipe").set({
        direction: Hammer.DIRECTION_VERTICAL,
        threshold: 10,
        velocity: 0.1,
      });

      // Add touch-action CSS
      pipContent.style.touchAction = "pan-x";
      pipContent.style.webkitTouchAction = "pan-x";

      swipeHammer.on("swipeup", function () {
        if (currentPage < pdf.numPages) {
          currentPage++;
          showPdfPage(currentPage);
        }
      });

      swipeHammer.on("swipedown", function () {
        if (currentPage > 1) {
          currentPage--;
          showPdfPage(currentPage);
        }
      });
    });
  }

  if (!pipViewer.dataset.doubleTapEnabled) {
    pipViewer.dataset.doubleTapEnabled = "true"; // prevent duplicates

    // ✅ Central toggle logic on pipContent
    pipContent.addEventListener("dblclick", () => {
      if (pipViewer.classList.contains("minimize-mode")) {
        maximizeViewer();
      } else {
        minimizeViewer();
      }
    });

    const tapHammer = new Hammer(pipContent);
    tapHammer.add(new Hammer.Tap({ event: "doubletap", taps: 2 }));
    tapHammer.on("doubletap", () => {
      if (pipViewer.classList.contains("minimize-mode")) {
        maximizeViewer();
      } else {
        minimizeViewer();
      }
    });
  }
}

function showPdfPage(pageNum) {
  const pipContent = document.getElementById("pipContent");
  if (!currentPdf) return;

  currentPdf.getPage(pageNum).then(function (page) {
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");

    // Get the exact pip viewer dimensions
    const pipViewer = document.getElementById("pipViewer");
    const headerHeight = document.getElementById("pipHeader").offsetHeight;
    const availableHeight = pipViewer.offsetHeight - headerHeight - 20; // 20px for padding
    const availableWidth = pipViewer.offsetWidth - 20; // 20px for padding

    // Get device pixel ratio for high DPI displays
    const pixelRatio = window.devicePixelRatio || 1;

    // Get PDF page dimensions
    const pdfWidth = page.view[2];
    const pdfHeight = page.view[3];

    // Calculate scale to fill pip viewer
    const scaleX = availableWidth / pdfWidth;
    const scaleY = availableHeight / pdfHeight;
    const baseScale = Math.min(scaleX, scaleY);

    // Create viewport with pixel ratio for sharp rendering
    const viewport = page.getViewport({ scale: baseScale * pixelRatio });

    // Set canvas size to match viewport with pixel ratio
    canvas.width = viewport.width;
    canvas.height = viewport.height;

    // Scale canvas display size back down
    canvas.style.width = `${viewport.width / pixelRatio}px`;
    canvas.style.height = `${viewport.height / pixelRatio}px`;

    // Setup canvas and container styles
    canvas.style.userSelect = "none";
    canvas.style.webkitUserSelect = "none";
    canvas.style.touchAction = "none";
    canvas.style.transformOrigin = "center center";
    canvas.style.position = "absolute";
    canvas.style.left = "50%";
    canvas.style.top = "50%";
    canvas.style.transform = "translate(-50%, -50%)";
    canvas.style.display = "block";

    // Clear and setup container
    pipContent.innerHTML = "";
    pipContent.style.position = "relative";
    pipContent.style.width = "100%";
    pipContent.style.height = "100%";
    pipContent.style.display = "flex";
    pipContent.style.alignItems = "center";
    pipContent.style.justifyContent = "center";
    pipContent.style.overflow = "hidden";
    pipContent.appendChild(canvas);

    let currentZoom = 1;
    let lastZoom = 1;
    let panX = 0;
    let panY = 0;
    let lastPanX = 0;
    let lastPanY = 0;

    function updateTransform() {
      canvas.style.transform = `translate(calc(-50% + ${panX}px), calc(-50% + ${panY}px)) scale(${currentZoom})`;
    }

    // Setup Hammer.js for touch gestures
    const hammer = new Hammer.Manager(canvas);

    // Add recognizers
    const pinch = new Hammer.Pinch();
    const pan = new Hammer.Pan({
      direction: Hammer.DIRECTION_ALL,
      threshold: 0,
    });

    // Add recognizers with proper order
    hammer.add([pinch, pan]);
    pinch.recognizeWith(pan);

    hammer.on("pinchstart", () => {
      lastZoom = currentZoom;
    });

    hammer.on("pinchmove", (e) => {
      currentZoom = Math.max(0.5, Math.min(lastZoom * e.scale, 4));
      updateTransform();
    });

    hammer.on("pinchend", () => {
      lastZoom = currentZoom;
    });

    hammer.on("panstart", () => {
      lastPanX = panX;
      lastPanY = panY;
    });

    hammer.on("panmove", (e) => {
      if (currentZoom > 1) {
        // Only allow panning when zoomed in
        panX = lastPanX + e.deltaX;
        panY = lastPanY + e.deltaY;
        updateTransform();
      } else {
        // When not zoomed, handle vertical swipe for page navigation
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
          if (e.deltaY < -50 && currentPage < currentPdf.numPages) {
            currentPage++;
            showPdfPage(currentPage);
          } else if (e.deltaY > 50 && currentPage > 1) {
            currentPage--;
            showPdfPage(currentPage);
          }
        }
      }
    });

    // Reset functionality
    document.getElementById("pipReset").onclick = () => {
      currentZoom = 1;
      panX = 0;
      panY = 0;
      lastPanX = 0;
      lastPanY = 0;
      updateTransform();
    };

    // Render the page with high quality
    const renderContext = {
      canvasContext: ctx,
      viewport: viewport,
      enableWebGL: true,
      renderInteractiveForms: true,
    };

    page.render(renderContext).promise.then(() => {
      // Add page indicator after successful render
      const pageIndicator = document.createElement("div");
      pageIndicator.className = "page-indicator";
      pageIndicator.style.position = "absolute";
      pageIndicator.style.bottom = "10px";
      pageIndicator.style.left = "50%";
      pageIndicator.style.transform = "translateX(-50%)";
      pageIndicator.style.background = "rgba(0, 0, 0, 0.5)";
      pageIndicator.style.color = "white";
      pageIndicator.style.padding = "5px 10px";
      pageIndicator.style.borderRadius = "15px";
      pageIndicator.style.zIndex = "1000";
      pageIndicator.textContent = `Page ${pageNum} of ${currentPdf.numPages}`;
      pipContent.appendChild(pageIndicator);
    });
  });
}

function minimizeViewer() {
  const pipViewer = document.getElementById("pipViewer");
  pipViewer.classList.remove("maximize-mode");
  pipViewer.classList.add("minimize-mode");

  // Reset styles that interfere with dragging
  pipViewer.style.transform = "none";
  pipViewer.style.top = "auto";
  pipViewer.style.left = "auto";
  pipViewer.style.right = "1rem";
  pipViewer.style.bottom = "1rem";

  // Set fixed dimensions for minimized mode
  pipViewer.style.width = "300px";
  pipViewer.style.height = "400px";

  document.body.classList.remove("no-scroll");
  document.getElementById("pipBackdrop").style.display = "none";
  document.getElementById("pipMinimize").classList.add("d-none");
  document.getElementById("pipMaximize").classList.remove("d-none");

  // Re-render PDF to fit new dimensions if PDF is loaded
  if (currentPdf && currentPage) {
    showPdfPage(currentPage);
  }
}

function maximizeViewer() {
  const pipViewer = document.getElementById("pipViewer");
  pipViewer.classList.remove("minimize-mode");
  pipViewer.classList.add("maximize-mode");

  // Reset position to avoid leftover styles from minimize mode
  pipViewer.style.top = "80px";
  pipViewer.style.left = "50%";
  pipViewer.style.bottom = "auto";
  pipViewer.style.right = "auto";
  pipViewer.style.transform = "translateX(-50%)";

  // Reset fixed dimensions
  pipViewer.style.width = "";
  pipViewer.style.height = "";

  document.body.classList.add("no-scroll");
  document.getElementById("pipBackdrop").style.display = "block";
  document.getElementById("pipMinimize").classList.remove("d-none");
  document.getElementById("pipMaximize").classList.add("d-none");

  // Re-render PDF to fit new dimensions if PDF is loaded
  if (currentPdf && currentPage) {
    showPdfPage(currentPage);
  }
}

document.getElementById("pipClose").onclick = () => {
  document.getElementById("pipViewer").classList.add("d-none");
  document.getElementById("pipContent").innerHTML = "";
  document.body.classList.remove("no-scroll");
  document.getElementById("pipBackdrop").style.display = "none";
  currentPdf = null;
};

document.addEventListener("DOMContentLoaded", function () {
  const btnMin = document.getElementById("pipMinimize");
  const btnMax = document.getElementById("pipMaximize");
  const btnClose = document.getElementById("pipClose");

  if (btnMin) btnMin.addEventListener("click", minimizeViewer);
  if (btnMax) btnMax.addEventListener("click", maximizeViewer);
  if (btnClose) {
    btnClose.addEventListener("click", () => {
      document.getElementById("pipViewer").classList.add("d-none");
      document.getElementById("pipContent").innerHTML = "";
      document.body.classList.remove("no-scroll");
      document.getElementById("pipBackdrop").style.display = "none";
      currentPdf = null;
    });
  }

  enableDraggableMinimizedViewer();
});

function setupPanZoom(img) {
  let scale = 1,
    panX = 0,
    panY = 0,
    lastX = 0,
    lastY = 0,
    lastScale = 1;

  function updateTransform() {
    scale = Math.min(Math.max(scale, 0.5), 4);
    img.style.transform = `translate(calc(-50% + ${panX}px), calc(-50% + ${panY}px)) scale(${scale})`;
  }

  // Set touch-action to none to prevent browser handling
  img.style.touchAction = "none";
  img.style.webkitTouchAction = "none";

  // Mouse wheel zoom
  img.addEventListener(
    "wheel",
    (e) => {
      e.preventDefault();
      if (e.ctrlKey || e.metaKey) {
        const delta = -e.deltaY / 500;
        scale *= 1 + delta;
        updateTransform();
      }
    },
    { passive: false }
  );

  const hammer = new Hammer.Manager(img, {
    touchAction: "none",
  });

  // Add recognizers
  const pinch = new Hammer.Pinch();
  const pan = new Hammer.Pan({
    direction: Hammer.DIRECTION_ALL,
    threshold: 0,
  });
  const tap = new Hammer.Tap({
    event: "doubletap",
    taps: 2,
  });

  // Add recognizers with proper order
  hammer.add([pinch, pan, tap]);

  // Enable pinch with pan
  pinch.recognizeWith(pan);

  hammer.on("pinchstart", () => {
    lastScale = scale;
  });

  hammer.on("pinchmove", (e) => {
    scale = Math.max(0.5, Math.min(lastScale * e.scale, 4));
    updateTransform();
  });

  hammer.on("pinchend", () => {
    lastScale = scale;
  });

  hammer.on("panstart", () => {
    lastX = panX;
    lastY = panY;
  });

  hammer.on("panmove", (e) => {
    panX = lastX + e.deltaX;
    panY = lastY + e.deltaY;
    updateTransform();
  });

  hammer.on("doubletap", () => {
    scale = scale === 1 ? 2 : 1;
    panX = 0;
    panY = 0;
    updateTransform();
  });

  document.getElementById("pipReset").onclick = () => {
    scale = 1;
    panX = 0;
    panY = 0;
    lastX = 0;
    lastY = 0;
    lastScale = 1;
    updateTransform();
  };
}

function enableDraggableMinimizedViewer() {
  const pipViewer = document.getElementById("pipViewer");

  let isDragging = false;
  let offsetX = 0;
  let offsetY = 0;

  function startDrag(x, y) {
    const rect = pipViewer.getBoundingClientRect();
    offsetX = x - rect.left;
    offsetY = y - rect.top;
    isDragging = true;
  }

  function doDrag(x, y) {
    if (!isDragging) return;

    const viewerWidth = pipViewer.offsetWidth;
    const viewerHeight = pipViewer.offsetHeight;
    const maxLeft = window.innerWidth - viewerWidth;
    const maxTop = window.innerHeight - viewerHeight;

    let newLeft = x - offsetX;
    let newTop = y - offsetY;

    newLeft = Math.max(0, Math.min(newLeft, maxLeft));
    newTop = Math.max(0, Math.min(newTop, maxTop));

    pipViewer.style.left = `${newLeft}px`;
    pipViewer.style.top = `${newTop}px`;
    pipViewer.style.right = "auto";
    pipViewer.style.bottom = "auto";
  }

  function endDrag() {
    isDragging = false;
  }

  // Mouse events
  pipViewer.addEventListener("mousedown", (e) => {
    if (!pipViewer.classList.contains("minimize-mode")) return;
    startDrag(e.clientX, e.clientY);
    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  });

  function onMouseMove(e) {
    doDrag(e.clientX, e.clientY);
  }

  function onMouseUp() {
    endDrag();
    document.removeEventListener("mousemove", onMouseMove);
    document.removeEventListener("mouseup", onMouseUp);
  }

  // Touch events
  pipViewer.addEventListener("touchstart", (e) => {
    if (!pipViewer.classList.contains("minimize-mode")) return;
    if (e.touches.length === 1) {
      const touch = e.touches[0];
      startDrag(touch.clientX, touch.clientY);
    }
  });

  pipViewer.addEventListener("touchmove", (e) => {
    if (!isDragging || e.touches.length !== 1) return;
    const touch = e.touches[0];
    doDrag(touch.clientX, touch.clientY);
  });

  pipViewer.addEventListener("touchend", () => {
    endDrag();
  });
}

// Add CSS for swipe hint
const style = document.createElement("style");
style.textContent = `
  .page-indicator {
    opacity: 0.8;
    transition: opacity 0.3s;
  }
  .page-indicator:hover {
    opacity: 1;
  }
`;
document.head.appendChild(style);
