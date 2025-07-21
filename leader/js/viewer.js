function debounce(func, delay = 50) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), delay);
  };
}

class DocumentViewer {
  enableCanvasDragging() {
    if (!this.targetElement) return;
    const canvas = this.targetElement;
    let isDragging = false;
    let startX = 0,
      startY = 0,
      scrollLeft = 0,
      scrollTop = 0;
    const content = this.content;

    // Mouse events
    canvas.onmousedown = (e) => {
      isDragging = true;
      startX = e.clientX;
      startY = e.clientY;
      scrollLeft = content.scrollLeft;
      scrollTop = content.scrollTop;
      canvas.style.cursor = "grabbing";
      document.body.style.userSelect = "none";
    };
    window.onmousemove = (e) => {
      if (!isDragging) return;
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;
      content.scrollLeft = scrollLeft - dx;
      content.scrollTop = scrollTop - dy;
    };
    window.onmouseup = () => {
      isDragging = false;
      canvas.style.cursor = "";
      document.body.style.userSelect = "";
    };

    // Touch events
    canvas.ontouchstart = (e) => {
      if (e.touches.length !== 1) return;
      isDragging = true;
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
      scrollLeft = content.scrollLeft;
      scrollTop = content.scrollTop;
    };
    canvas.ontouchmove = (e) => {
      if (!isDragging || e.touches.length !== 1) return;
      const dx = e.touches[0].clientX - startX;
      const dy = e.touches[0].clientY - startY;
      content.scrollLeft = scrollLeft - dx;
      content.scrollTop = scrollTop - dy;
    };
    canvas.ontouchend = () => {
      isDragging = false;
    };
  }
  static activeViewers = {};
  static lastViewerState = null;

  static create(id, title, fileUrl, files = []) {
    if (DocumentViewer.activeViewers[id]) {
      DocumentViewer.activeViewers[id].remove();
    }

    // Use last viewer state if available, otherwise create new state
    const viewerState =
      id === "viewerWI" && DocumentViewer.lastViewerState
        ? DocumentViewer.lastViewerState
        : {
            width: 720,
            height: 560,
            scale: 1,
            translateX: 100,
            translateY: 100,
          };

    const viewer = new DocumentViewer(id, title, fileUrl, files, viewerState);

    DocumentViewer.activeViewers[id] = viewer;

    // Store the state for work instruction viewers
    if (id === "viewerWI") {
      DocumentViewer.lastViewerState = viewerState;
    }

    return viewer;
  }

  constructor(id, title, fileUrl, files = [], options = {}) {
    this.id = id;
    this.title = title;
    this.fileUrl = fileUrl;
    this.files = files;
    this.scale = 1.0;
    this.currentPage = 1;
    this.renderTask = null;
    this.targetElement = null;
    this.pdfDoc = null;
    this.isDragging = false;

    this.customWidth = options.width || 720;
    this.customHeight = options.height || 560;
    this.customScale = options.scale || 1;
    this.translateX = options.translateX ?? 100;
    this.translateY = options.translateY ?? 100;

    this.initViewer();
    this.loadFile();
  }

  initViewer() {
    const ext = this.fileUrl.split(".").pop().toLowerCase();
    this.isImage = ["jpg", "jpeg", "png", "gif", "bmp", "webp"].includes(ext);
    this.isPDF = ext === "pdf";
    if (!this.isImage && !this.isPDF) {
      alert("Unsupported file type: " + ext);
      return;
    }

    this.viewer = document.createElement("div");
    this.viewer.id = this.id;

    Object.assign(this.viewer.style, {
      position: "fixed",
      left: `${this.translateX}px`,
      top: `${this.translateY}px`,
      width: `${this.customWidth}px`,
      height: `${this.customHeight}px`,
      transform: `scale(${this.customScale})`,
      transformOrigin: "top left",
      background: "#ffffff",
      borderRadius: "10px",
      border: "1px solid #ddd",
      boxShadow: "0 8px 24px rgba(0, 0, 0, 0.15)",
      zIndex: 10000,
      display: "flex",
      flexDirection: "column",
      resize: "both",
      overflow: "auto",
      boxSizing: "border-box",
      fontFamily: "Segoe UI, Roboto, sans-serif",
    });
    this.viewer.dataset.scale = this.customScale;
    this.createHeader();
    this.createToolbar();

    this.content = document.createElement("div");
    Object.assign(this.content.style, {
      flex: 1,
      position: "relative",
      overflow: "auto",
      display: "flex",
      justifyContent: "center",
      alignItems: "center",
      background: "#fafafa",
    });

    this.viewer.append(this.header, this.toolbar, this.content);
    this.addResizeHandle();
    document.body.appendChild(this.viewer);

    this.addDragFunctionality();
    this.addPinchResize();
    this.addStyleFixes();

    // Update last viewer state on resize
    const resizeObserver = new ResizeObserver(
      debounce(() => {
        if (this.id === "viewerWI" && DocumentViewer.lastViewerState) {
          DocumentViewer.lastViewerState.width = this.viewer.offsetWidth;
          DocumentViewer.lastViewerState.height = this.viewer.offsetHeight;
          DocumentViewer.lastViewerState.scale =
            parseFloat(this.viewer.dataset.scale) || 1;
          DocumentViewer.lastViewerState.translateX = this.translateX;
          DocumentViewer.lastViewerState.translateY = this.translateY;
        }
      }, 100)
    );
    resizeObserver.observe(this.viewer);
  }

  addPinchResize() {
    const hammer = new Hammer(this.viewer);
    hammer.get("pinch").set({ enable: true });

    let baseScale = 1;

    hammer.on("pinchstart", () => {
      const currentScale = parseFloat(this.viewer.dataset.scale) || 1;
      baseScale = currentScale;
    });

    hammer.on("pinchmove", (e) => {
      const newScale = Math.max(0.5, Math.min(2.5, baseScale * e.scale));
      this.viewer.style.transform = `scale(${newScale})`;
      this.viewer.dataset.scale = newScale;
      this.viewer.style.transformOrigin = "top left";

      // Update last viewer state for WI
      if (this.id === "viewerWI" && DocumentViewer.lastViewerState) {
        DocumentViewer.lastViewerState.scale = newScale;
      }
    });

    hammer.on("pinchend", () => {
      // Optional: persist scale or round it
    });
  }

  addResizeHandle() {
    const handle = document.createElement("div");
    Object.assign(handle.style, {
      width: "16px",
      height: "16px",
      position: "absolute",
      right: "0",
      bottom: "0",
      cursor: "nwse-resize",
      background: "transparent",
      zIndex: 10001,
    });

    handle.addEventListener("pointerdown", (e) => {
      e.preventDefault();
      const startX = e.clientX;
      const startY = e.clientY;
      const startWidth = this.viewer.offsetWidth;
      const startHeight = this.viewer.offsetHeight;

      const onPointerMove = (e) => {
        const newWidth = Math.max(300, startWidth + (e.clientX - startX));
        const newHeight = Math.max(200, startHeight + (e.clientY - startY));
        this.viewer.style.width = `${newWidth}px`;
        this.viewer.style.height = `${newHeight}px`;

        if (this.isPDF) this.renderPDF();
        if (this.isImage)
          this.targetElement.style.transform = `scale(${this.scale})`;

        // Update last viewer state for WI
        if (this.id === "viewerWI" && DocumentViewer.lastViewerState) {
          DocumentViewer.lastViewerState.width = newWidth;
          DocumentViewer.lastViewerState.height = newHeight;
        }
      };

      const onPointerUp = () => {
        document.removeEventListener("pointermove", onPointerMove);
        document.removeEventListener("pointerup", onPointerUp);
      };

      document.addEventListener("pointermove", onPointerMove);
      document.addEventListener("pointerup", onPointerUp);
    });

    this.viewer.appendChild(handle);
  }

  createHeader() {
    this.header = document.createElement("div");
    Object.assign(this.header.style, {
      background: "#1e1e2f",
      color: "#fff",
      padding: "10px 16px",
      display: "flex",
      alignItems: "center",
      cursor: "move",
      fontSize: "16px",
      fontWeight: "500",
      userSelect: "none",
      gap: "12px",
    });

    const span = document.createElement("span");
    span.innerText = this.title;
    span.style.flexShrink = "0";

    this.navContainer = document.createElement("div");
    Object.assign(this.navContainer.style, {
      display: "flex",
      gap: "6px",
      flexWrap: "wrap",
      flex: "1",
    });

    if (this.files.length > 0) this.createNavigationButtons();

    this.closeBtn = document.createElement("span");
    this.closeBtn.innerHTML = "&times;";
    Object.assign(this.closeBtn.style, {
      cursor: "pointer",
      fontSize: "22px",
      userSelect: "none",
      padding: "0 6px",
      flexShrink: "0",
    });
    this.closeBtn.onmouseover = () => (this.closeBtn.style.color = "#ff4d4d");
    this.closeBtn.onmouseout = () => (this.closeBtn.style.color = "#fff");
    this.closeBtn.onclick = (e) => {
      e.stopPropagation();
      this.remove();
    };

    this.header.append(span, this.navContainer, this.closeBtn);
  }

  createNavigationButtons() {
    const currentOp = this.title.match(/\(P(\w+)\)/)?.[1];
    this.navContainer.innerHTML = "";
    this.files
      .sort((a, b) => a.operator - b.operator)
      .forEach(({ operator, url }) => {
        const btn = document.createElement("button");
        btn.dataset.operator = operator;
        btn.textContent = `P${operator}`;
        Object.assign(btn.style, {
          padding: "4px 8px",
          margin: "0 2px",
          border: "1px solid #ccc",
          background: operator == currentOp ? "#0d6efd" : "#333",
          color: "#fff",
          cursor: "pointer",
          borderRadius: "4px",
        });
        if (operator == currentOp) {
          btn.style.boxShadow = "0 0 0 2px rgba(13,110,253,0.5)";
        }
        btn.onclick = (e) => {
          e.stopPropagation();
          [...this.navContainer.children].forEach((b) => {
            const isActive = b.dataset.operator == operator;
            b.style.background = isActive ? "#0d6efd" : "#333";
            b.style.borderColor = isActive ? "#0d6efd" : "#ccc";
            b.style.boxShadow = isActive
              ? "0 0 0 2px rgba(13,110,253,0.5)"
              : "";
          });
          DocumentViewer.create(
            "viewerWI",
            `Work Instruction (P${operator})`,
            url,
            this.files
          );
        };
        this.navContainer.appendChild(btn);
      });
  }

  createToolbar() {
    this.toolbar = document.createElement("div");
    this.toolbar.innerHTML = `
      <button id="prevPage">◀️</button>
      <span id="pageInfo">Page 1</span>
      <button id="nextPage">▶️</button>
      <button id="zoomOut">−</button>
      <button id="zoomIn">＋</button>
      <button id="resetZoom">Reset</button>
      <button id="fitZoom">Fit</button>
      <button id="downloadFile">📥</button>
    `;
    Object.assign(this.toolbar.style, {
      display: this.isPDF ? "flex" : "none",
      background: "#f2f2f5",
      padding: "8px 12px",
      gap: "8px",
      alignItems: "center",
      borderBottom: "1px solid #ccc",
      fontSize: "14px",
    });
    document.body.appendChild(this.toolbar); // ensure always top layer
    setTimeout(() => {
      this.toolbar.querySelectorAll("button").forEach((btn) => {
        Object.assign(btn.style, {
          padding: "4px 10px",
          border: "1px solid #ccc",
          borderRadius: "4px",
          background: "#fff",
          cursor: "pointer",
        });
      });
    }, 0);
  }

  loadFile() {
    if (this.isPDF) {
      this.loadPDF();
    } else {
      this.loadImage();
    }
  }

  loadImage() {
    const img = document.createElement("img");
    img.src = this.fileUrl;
    Object.assign(img.style, {
      maxWidth: "100%",
      maxHeight: "100%",
      display: "block",
      margin: "auto",
      transition: "transform 0.2s ease-out",
      transformOrigin: "center center",
    });
    // Fit logic as a function so we can call it on load and on resize
    const fitImage = () => {
      if (!img.naturalWidth || !img.naturalHeight) return;
      const contentW = this.content.clientWidth;
      const contentH = this.content.clientHeight;
      const scaleW = contentW / img.naturalWidth;
      const scaleH = contentH / img.naturalHeight;
      this.scale = Math.min(scaleW, scaleH, 1);
      img.style.transform = `scale(${this.scale})`;
    };
    img.onload = fitImage;
    // Observe content area for resize to auto-fit image
    this._imageResizeObserver?.disconnect();
    this._imageResizeObserver = new ResizeObserver(fitImage);
    this._imageResizeObserver.observe(this.content);
    this.targetElement = img;
    this.content.innerHTML = "";
    this.content.appendChild(img);
    this.addZoomListeners();
    // Add fit button support for images
    if (this.toolbar) {
      const fitBtn = this.toolbar.querySelector("#fitZoom");
      if (fitBtn) {
        fitBtn.onclick = fitImage;
      }
    }
  }

  loadPDF() {
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js";
    pdfjsLib.getDocument(this.fileUrl).promise.then((pdf) => {
      this.pdfDoc = pdf;
      this.currentPage = 1;
      // Fit PDF to content area on open (using first page)
      this.fitPDFToCurrentPage();
      // Observe content area for resize to auto-fit PDF
      this._pdfResizeObserver?.disconnect();
      this._pdfResizeObserver = new ResizeObserver(() => {
        this.fitPDFToCurrentPage();
      });
      this._pdfResizeObserver.observe(this.content);

      const t = this.toolbar;
      t.querySelector("#zoomOut").onclick = () =>
        this.applyZoom(this.scale - 0.1);
      t.querySelector("#zoomIn").onclick = () =>
        this.applyZoom(this.scale + 0.1);
      t.querySelector("#resetZoom").onclick = () => this.applyZoom(1);
      t.querySelector("#fitZoom").onclick = () => {
        this.fitPDFToCurrentPage();
      };
      t.querySelector("#prevPage").onclick = () => {
        if (this.currentPage > 1) {
          this.currentPage--;
          this.fitPDFToCurrentPage();
        }
      };
      t.querySelector("#nextPage").onclick = () => {
        if (this.currentPage < pdf.numPages) {
          this.currentPage++;
          this.fitPDFToCurrentPage();
        }
      };
      t.querySelector("#downloadFile").onclick = () => {
        const a = document.createElement("a");
        a.href = this.fileUrl;
        a.download = this.fileUrl.split("/").pop();
        a.click();
      };
      this.addZoomListeners();
    });
  }

  fitPDFToCurrentPage() {
    if (!this.pdfDoc) return;
    this.pdfDoc.getPage(this.currentPage).then((page) => {
      const viewport = page.getViewport({ scale: 1 });
      this._pdfBaseWidth = viewport.width;
      this._pdfBaseHeight = viewport.height;

      // Adjust viewer size to match PDF aspect ratio and maximize in window
      const margin = 40;
      const maxW = window.innerWidth - margin;
      const maxH = window.innerHeight - margin;
      const pdfAspect = viewport.width / viewport.height;
      let viewerW = maxW;
      let viewerH = Math.round(viewerW / pdfAspect);
      if (viewerH > maxH) {
        viewerH = maxH;
        viewerW = Math.round(viewerH * pdfAspect);
      }
      this.viewer.style.width = `${viewerW}px`;
      this.viewer.style.height = `${viewerH}px`;

      this.scale = this.getFitScaleForPDF();
      this.renderPDF();
    });
  }

  getFitScaleForPDF() {
    // Always fit the PDF page fully inside the content area (contain, not cover)
    if (!this.pdfDoc || !this.content) return 1;
    const baseW = this._pdfBaseWidth || 595;
    const baseH = this._pdfBaseHeight || 842;
    const contentW = this.content.clientWidth;
    const contentH = this.content.clientHeight;
    // Use the smaller scale so the PDF fits entirely (like background-size: contain)
    return Math.min(contentW / baseW, contentH / baseH);
  }

  addZoomListeners() {
    this.content.onwheel = (e) => {
      e.preventDefault();
      this.applyZoom(this.scale + (e.deltaY < 0 ? 0.1 : -0.1));
    };
    new Hammer(this.content)
      .get("pinch")
      .set({ enable: true })
      .on("pinch", (e) => this.applyZoom(this.scale * e.scale));
  }

  renderPDF() {
    if (!this.pdfDoc) return;
    if (this.renderTask) this.renderTask.cancel();
    this.pdfDoc.getPage(this.currentPage).then((page) => {
      const base = page.getViewport({ scale: 1 });
      this._pdfBaseWidth = base.width;
      this._pdfBaseHeight = base.height;
      // Always fit the content area (contain)
      const contentW = this.content.clientWidth;
      const contentH = this.content.clientHeight;
      const scale = this.getFitScaleForPDF();
      const viewport = page.getViewport({ scale });
      let canvas = this.targetElement || document.createElement("canvas");
      // Set canvas to content area size and center it using flex
      Object.assign(canvas, { width: contentW, height: contentH });
      canvas.style.display = "block";
      canvas.style.margin = "0 auto";
      canvas.style.background = "#fff";
      canvas.style.position = "static";
      canvas.style.width = "100%";
      canvas.style.height = "100%";
      this.content.style.position = "relative";
      this.content.style.display = "flex";
      this.content.style.alignItems = "center";
      this.content.style.justifyContent = "center";
      this.content.style.overflow = "hidden";
      if (!this.targetElement) {
        this.content.innerHTML = "";
        this.content.appendChild(canvas);
        this.targetElement = canvas;
      }
      // Render PDF page to an offscreen canvas, then draw centered in visible canvas
      const offCanvas = document.createElement("canvas");
      offCanvas.width = viewport.width;
      offCanvas.height = viewport.height;
      const renderTask = page.render({
        canvasContext: offCanvas.getContext("2d"),
        viewport,
      });
      renderTask.promise
        .then(() => {
          // Draw the PDF page scaled to fit and centered in the canvas
          const ctx = canvas.getContext("2d");
          ctx.clearRect(0, 0, contentW, contentH);
          // Calculate scale to fit (contain) and center
          const scaleW = contentW / offCanvas.width;
          const scaleH = contentH / offCanvas.height;
          const fitScale = Math.min(scaleW, scaleH);
          const drawW = offCanvas.width * fitScale;
          const drawH = offCanvas.height * fitScale;
          const dx = Math.floor((contentW - drawW) / 2);
          const dy = Math.floor((contentH - drawH) / 2);
          ctx.drawImage(
            offCanvas,
            0,
            0,
            offCanvas.width,
            offCanvas.height,
            dx,
            dy,
            drawW,
            drawH
          );
        })
        .catch((err) => {
          if (err.name !== "RenderingCancelledException") console.error(err);
        });
      this.renderTask = renderTask;
      this.toolbar.querySelector(
        "#pageInfo"
      ).innerText = `Page ${this.currentPage} / ${this.pdfDoc.numPages}`;
    });
  }

  applyZoom = debounce((newScale) => {
    this.scale = Math.max(0.3, Math.min(4, newScale));
    if (this.isImage) {
      this.targetElement.style.transform = `scale(${this.scale})`;
    } else {
      this.renderPDF();
    }
  });

  addDragFunctionality() {
    // Mouse drag
    this.header.onmousedown = (e) => {
      this.isDragging = true;
      this.dragStartX = e.clientX - this.translateX;
      this.dragStartY = e.clientY - this.translateY;
      document.onmousemove = this.handleDrag;
      document.onmouseup = this.stopDrag;
    };

    // Touch drag (for tablets)
    this.header.ontouchstart = (e) => {
      if (e.touches.length !== 1) return;
      this.isDragging = true;
      this.dragStartX = e.touches[0].clientX - this.translateX;
      this.dragStartY = e.touches[0].clientY - this.translateY;
      document.ontouchmove = this.handleTouchDrag;
      document.ontouchend = this.stopTouchDrag;
    };
  }

  handleTouchDrag = (e) => {
    if (!this.isDragging || e.touches.length !== 1) return;
    this.translateX = e.touches[0].clientX - this.dragStartX;
    this.translateY = e.touches[0].clientY - this.dragStartY;
    Object.assign(this.viewer.style, {
      left: `${this.translateX}px`,
      top: `${this.translateY}px`,
    });

    // Update last viewer state for WI
    if (this.id === "viewerWI" && DocumentViewer.lastViewerState) {
      DocumentViewer.lastViewerState.translateX = this.translateX;
      DocumentViewer.lastViewerState.translateY = this.translateY;
    }
  };

  stopTouchDrag = () => {
    this.isDragging = false;
    document.ontouchmove = null;
    document.ontouchend = null;
  };

  handleDrag = (e) => {
    if (!this.isDragging) return;
    this.translateX = e.clientX - this.dragStartX;
    this.translateY = e.clientY - this.dragStartY;
    Object.assign(this.viewer.style, {
      left: `${this.translateX}px`,
      top: `${this.translateY}px`,
    });

    // Update last viewer state for WI
    if (this.id === "viewerWI" && DocumentViewer.lastViewerState) {
      DocumentViewer.lastViewerState.translateX = this.translateX;
      DocumentViewer.lastViewerState.translateY = this.translateY;
    }
  };

  stopDrag = () => {
    this.isDragging = false;
    document.onmousemove = null;
    document.onmouseup = null;
  };

  addStyleFixes() {
    const css = `
      #${this.id} .header, #${this.id} .toolbar, #${this.id} .nav-btn,
      #${this.id} .closeBtn {
        z-index: 10001;
      }
    `;
    document.head.insertAdjacentHTML("beforeend", `<style>${css}</style>`);
  }

  remove() {
    this.viewer.remove();
    if (this.toolbar) this.toolbar.remove();
    delete DocumentViewer.activeViewers[this.id];
    if (
      this.id === "viewerWI" &&
      Object.keys(DocumentViewer.activeViewers).length === 0
    ) {
      document.getElementById("wi-nav-header")?.remove();
    }
  }
}

// Bind buttons
document.getElementById("btnDrawing")?.addEventListener("click", function () {
  const f = this.dataset.file;
  if (f) DocumentViewer.create("viewerDrawing", "Drawing", f);
});

document
  .getElementById("btnWorkInstruction")
  ?.addEventListener("click", async function () {
    const hostnameId = new URLSearchParams(window.location.search).get(
      "hostname_id"
    );
    try {
      const res = await fetch(
        `../controller/dor-documents-viewer.php?hostname_id=${hostnameId}&json=1`
      );
      const files = (await res.json()).files || [];
      if (!files.length) return alert("No work instructions found.");

      const headerId = "wi-nav-header";
      document.getElementById(headerId)?.remove();

      const navBar = document.createElement("div");
      navBar.id = headerId;

      const title = document.createElement("span");
      title.textContent = "Work Instruction";
      title.style.fontWeight = "bold";
      navBar.append(title);

      files
        .sort((a, b) => a.operator - b.operator)
        .forEach((f) => {
          const b = document.createElement("button");
          b.textContent = `P${f.operator}`;
          b.dataset.operator = f.operator;
          Object.assign(b.style, {
            background: "#333",
            color: "#fff",
            border: "1px solid #ccc",
            padding: "4px 8px",
            cursor: "pointer",
            borderRadius: "4px",
          });
          b.onclick = () => {
            navBar.querySelectorAll("button[data-operator]").forEach((x) => {
              const isAct = x.dataset.operator === b.dataset.operator;
              x.style.background = isAct ? "#0d6efd" : "#333";
              x.style.borderColor = isAct ? "#0d6efd" : "#ccc";
              x.style.boxShadow = isAct ? "0 0 0 2px rgba(13,110,253,0.5)" : "";
            });
            DocumentViewer.create(
              "viewerWI",
              `Work Instruction (P${f.operator})`,
              f.url,
              files
            );
          };
          navBar.append(b);
        });

      const closeBtn = document.createElement("button");
      closeBtn.textContent = "✖";
      Object.assign(closeBtn.style, {
        marginLeft: "auto",
        padding: "4px 8px",
        cursor: "pointer",
      });
      closeBtn.onclick = () => {
        navBar.remove();
        DocumentViewer.activeViewers["viewerWI"]?.remove();
        DocumentViewer.lastViewerState = null;
      };
      navBar.append(closeBtn);

      document.body.prepend(navBar);
      navBar.querySelector("button[data-operator]")?.click();
    } catch (err) {
      console.error(err);
      alert("Failed to load work instructions.");
    }
  });

document.getElementById("btnPrepCard")?.addEventListener("click", function () {
  const f = this.dataset.file;
  if (f) DocumentViewer.create("viewerPrepCard", "Preparation Card", f);
});
