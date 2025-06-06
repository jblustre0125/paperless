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
  // Reset all state variables
  currentType = type;
  currentPath = path;
  currentScale = 1;
  currentPage = 1;
  currentPdf = null;

  const pipViewer = document.getElementById("pipViewer");
  const pipContent = document.getElementById("pipContent");
  const btnMin = document.getElementById("pipMinimize");
  const btnMax = document.getElementById("pipMaximize");
  const pipBackdrop = document.getElementById("pipBackdrop");

  // Clear all content and styles first
  pipContent.innerHTML = "";
  pipViewer.removeAttribute("style");
  pipContent.removeAttribute("style");
  pipViewer.classList.remove("pdf-mode");

  // Set new classes
  pipViewer.className = "pip-viewer";
  pipViewer.classList.add("maximize-mode");
  if (type === "pdf") {
    pipViewer.classList.add("pdf-mode");
  }

  // Show/hide appropriate buttons
  btnMin.classList.remove("d-none");
  btnMax.classList.add("d-none");
  document.body.classList.add("no-scroll");
  pipBackdrop.style.display = "block";

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

      // Set up unified touch handling for page navigation
      const pageSwipeHandler = new Hammer.Manager(pipContent);

      // Configure vertical pan detection
      const pan = new Hammer.Pan({
        direction: Hammer.DIRECTION_VERTICAL,
        threshold: 50,
      });
      pageSwipeHandler.add(pan);

      // Track swipe state
      let swipeStartY = 0;
      let isProcessingSwipe = false;

      pageSwipeHandler.on("panstart", function (e) {
        swipeStartY = e.center.y;
        isProcessingSwipe = false;
      });

      pageSwipeHandler.on("panmove", function (e) {
        if (isProcessingSwipe) return;

        const diff = swipeStartY - e.center.y;
        const absDiff = Math.abs(diff);

        if (absDiff > 50) {
          isProcessingSwipe = true;

          if (diff > 0) {
            // Swipe up - go to next page
            if (currentPage < pdf.numPages) {
              currentPage++;
              showPdfPage(currentPage);
            }
          } else {
            // Swipe down - go to previous page
            if (currentPage > 1) {
              currentPage--;
              showPdfPage(currentPage);
            }
          }
        }
      });

      pageSwipeHandler.on("panend", function () {
        isProcessingSwipe = false;
      });

      // Mouse wheel
      pipContent.addEventListener(
        "wheel",
        function (e) {
          const currentTime = Date.now();
          if (currentTime - lastPageChangeTime < PAGE_CHANGE_DELAY) return;

          if (e.deltaY > 0 && currentPage < pdf.numPages) {
            currentPage++;
            showPdfPage(currentPage);
            lastPageChangeTime = currentTime;
          } else if (e.deltaY < 0 && currentPage > 1) {
            currentPage--;
            showPdfPage(currentPage);
            lastPageChangeTime = currentTime;
          }
          e.preventDefault();
        },
        { passive: false }
      );

      // Mouse click for page navigation
      pipContent.addEventListener("click", function (e) {
        // Skip if event originated from touch
        if (e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents) {
          return;
        }

        const currentTime = Date.now();
        if (currentTime - lastPageChangeTime < PAGE_CHANGE_DELAY) return;

        const rect = pipContent.getBoundingClientRect();
        const y = e.clientY - rect.top;

        if (y < rect.height / 2 && currentPage > 1) {
          currentPage--;
          showPdfPage(currentPage);
          lastPageChangeTime = currentTime;
        } else if (y >= rect.height / 2 && currentPage < pdf.numPages) {
          currentPage++;
          showPdfPage(currentPage);
          lastPageChangeTime = currentTime;
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
    const isMinimized = pipViewer.classList.contains("minimize-mode");

    // Get actual viewer dimensions based on mode and document type
    const isWorkInstruction = currentPath
      .toLowerCase()
      .includes("work_instruction");
    let viewerWidth, viewerHeight;

    // Force a reflow to ensure we get accurate dimensions
    pipViewer.style.display = "none";
    pipViewer.offsetHeight; // trigger reflow
    pipViewer.style.display = "";

    if (isMinimized) {
      // In minimized mode, use the actual PiP viewer size
      viewerWidth = pipViewer.clientWidth;
      viewerHeight = pipViewer.clientHeight;
    } else {
      // In maximized mode
      if (isWorkInstruction) {
        // For Work Instructions: Use window width directly
        viewerWidth = window.innerWidth;
        viewerHeight = window.innerHeight;
      } else {
        // For Prep Cards: Use actual viewer size
        viewerWidth = pipViewer.clientWidth;
        viewerHeight = pipViewer.clientHeight;
      }
    }

    // Get PDF page dimensions
    const pdfWidth = page.view[2];
    const pdfHeight = page.view[3];

    // Get device pixel ratio for high DPI displays
    const pixelRatio = window.devicePixelRatio || 1;

    // Calculate scale based on document type and mode
    let baseScale;
    if (isMinimized) {
      // Minimize mode
      if (isWorkInstruction) {
        // Work Instructions - keep original scale
        const scaleX = (viewerWidth - 20) / pdfWidth;
        const scaleY = (viewerHeight - headerHeight - 20) / pdfHeight;
        baseScale = Math.min(scaleX, scaleY) * 0.8;
      } else {
        // Prep Cards - increase scale
        const scaleX = (viewerWidth - 20) / pdfWidth;
        const scaleY = (viewerHeight - headerHeight - 20) / pdfHeight;
        baseScale = Math.min(scaleX, scaleY) * 1.5;
      }
    } else if (isWorkInstruction) {
      // Work Instructions maximize mode - force to fill viewer width
      baseScale = 10.0; // Testing with very large scale
    } else {
      // Prep Cards maximize mode - fit to window width exactly
      baseScale = viewerWidth / pdfWidth;
    }

    let viewport;
    if (isWorkInstruction && !isMinimized) {
      // For Work Instructions in maximize mode, force it to fill the viewer
      const containerWidth = pipViewer.clientWidth;
      const containerHeight = pipViewer.clientHeight - headerHeight;
      const scale =
        Math.min(containerWidth / pdfWidth, containerHeight / pdfHeight) * 1.8;
      viewport = page.getViewport({ scale: scale * pixelRatio });

      // Set canvas size to match container
      canvas.width = containerWidth * pixelRatio;
      canvas.height = containerHeight * pixelRatio;

      // Scale display size
      canvas.style.width = `${containerWidth}px`;
      canvas.style.height = `${containerHeight}px`;
    } else {
      // Normal viewport creation for other cases
      viewport = page.getViewport({ scale: baseScale * pixelRatio });

      // Set canvas size to match viewport
      canvas.width = viewport.width;
      canvas.height = viewport.height;

      // Scale display size
      canvas.style.width = `${viewport.width / pixelRatio}px`;
      canvas.style.height = `${viewport.height / pixelRatio}px`;
    }

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
    pipContent.removeAttribute("style");
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
      }
      // Remove page navigation from canvas pan handler
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
      // Add page indicator after successful render only if not in minimize mode
      if (!pipViewer.classList.contains("minimize-mode")) {
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
      }
    });
  });
}

function minimizeViewer() {
  const pipViewer = document.getElementById("pipViewer");

  // Remove all styles first
  pipViewer.removeAttribute("style");

  // Update classes
  pipViewer.classList.remove("maximize-mode");
  pipViewer.classList.add("minimize-mode");

  // Set minimal required styles for minimize mode - increased size for tablet
  pipViewer.style.right = "1rem";
  pipViewer.style.bottom = "1rem";
  pipViewer.style.width = "400px"; // Increased from 300px
  pipViewer.style.height = "300px"; // Increased from 200px

  // Hide page indicator in minimize mode
  const pageIndicator = document.querySelector(".page-indicator");
  if (pageIndicator) {
    pageIndicator.style.display = "none";
  }

  // Update UI state
  document.body.classList.remove("no-scroll");
  document.getElementById("pipBackdrop").style.display = "none";
  document.getElementById("pipMinimize").classList.add("d-none");
  document.getElementById("pipMaximize").classList.remove("d-none");

  // Re-render content based on type
  if (currentType === "pdf" && currentPdf && currentPage) {
    showPdfPage(currentPage);
  } else if (currentType === "image") {
    const img = document.getElementById("pipImage");
    if (img) {
      img.style.transform = "translate(-50%, -50%) scale(1)";
    }
  }
}

function maximizeViewer() {
  const pipViewer = document.getElementById("pipViewer");

  // Remove all styles first
  pipViewer.removeAttribute("style");

  // Update classes
  pipViewer.classList.remove("minimize-mode");
  pipViewer.classList.add("maximize-mode");

  // Show page indicator in maximize mode
  const pageIndicator = document.querySelector(".page-indicator");
  if (pageIndicator) {
    pageIndicator.style.display = "block";
  }

  // Update UI state
  document.body.classList.add("no-scroll");
  document.getElementById("pipBackdrop").style.display = "block";
  document.getElementById("pipMaximize").classList.add("d-none");
  document.getElementById("pipMinimize").classList.remove("d-none");

  // Re-render content based on type
  if (currentType === "pdf" && currentPdf && currentPage) {
    showPdfPage(currentPage);
  } else if (currentType === "image") {
    const img = document.getElementById("pipImage");
    if (img) {
      img.style.transform = "translate(-50%, -50%) scale(1)";
    }
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
  // Get all control buttons
  const btnMin = document.getElementById("pipMinimize");
  const btnMax = document.getElementById("pipMaximize");
  const btnReset = document.getElementById("pipReset");
  const btnClose = document.getElementById("pipClose");

  // Minimize button
  if (btnMin) {
    btnMin.addEventListener("click", (e) => {
      e.stopPropagation(); // Prevent event from bubbling to header
      minimizeViewer();
    });
  }

  // Maximize button
  if (btnMax) {
    btnMax.addEventListener("click", (e) => {
      e.stopPropagation(); // Prevent event from bubbling to header
      maximizeViewer();
      // Re-render content based on type after maximizing
      if (currentType === "pdf" && currentPdf && currentPage) {
        showPdfPage(currentPage);
      } else if (currentType === "image") {
        const img = document.getElementById("pipImage");
        if (img) {
          img.style.transform = "translate(-50%, -50%) scale(1)";
        }
      }
    });
  }

  // Reset button
  if (btnReset) {
    btnReset.addEventListener("click", (e) => {
      e.stopPropagation(); // Prevent event from bubbling to header
      if (currentType === "pdf" && currentPdf && currentPage) {
        showPdfPage(currentPage);
      } else if (currentType === "image") {
        const img = document.getElementById("pipImage");
        if (img) {
          img.style.transform = "translate(-50%, -50%) scale(1)";
        }
      }
    });
  }

  // Close button
  if (btnClose) {
    btnClose.addEventListener("click", (e) => {
      e.stopPropagation(); // Prevent event from bubbling to header
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
  const pipHeader = document.getElementById("pipHeader");

  let isDragging = false;
  let offsetX = 0;
  let offsetY = 0;

  function startDrag(x, y) {
    const rect = pipViewer.getBoundingClientRect();
    offsetX = x - rect.left;
    offsetY = y - rect.top;
    isDragging = true;
    pipHeader.style.cursor = "grabbing";
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
    pipHeader.style.cursor = "grab";
  }

  // Mouse events for header
  pipHeader.addEventListener("mousedown", (e) => {
    // Only allow dragging from header area, not from buttons
    if (
      !pipViewer.classList.contains("minimize-mode") ||
      e.target.closest(".pip-btn")
    )
      return;
    e.preventDefault(); // Prevent text selection
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

  // Touch events for header
  pipHeader.addEventListener("touchstart", (e) => {
    // Only allow dragging from header area, not from buttons
    if (
      !pipViewer.classList.contains("minimize-mode") ||
      e.target.closest(".pip-btn")
    )
      return;
    if (e.touches.length === 1) {
      e.preventDefault(); // Prevent scrolling
      const touch = e.touches[0];
      startDrag(touch.clientX, touch.clientY);
    }
  });

  document.addEventListener(
    "touchmove",
    (e) => {
      if (!isDragging || e.touches.length !== 1) return;
      e.preventDefault(); // Prevent scrolling while dragging
      const touch = e.touches[0];
      doDrag(touch.clientX, touch.clientY);
    },
    { passive: false }
  );

  pipHeader.addEventListener("touchend", () => {
    endDrag();
  });

  // Update header cursor style
  pipHeader.style.cursor = "grab";
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

// Add pageshow event handler to reinitialize when navigating back
window.onpageshow = function (event) {
  if (event.persisted) {
    // Page is loaded from cache (back/forward navigation)
    document.dispatchEvent(new Event("DOMContentLoaded"));
  }
};
