function debounce(func, delay = 50) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), delay);
  };
}

class DocumentViewer {
  static activeViewers = {};

  static create(id, title, fileUrl, files = []) {
    if (DocumentViewer.activeViewers[id]) {
      DocumentViewer.activeViewers[id].remove();
    }
    const viewer = new DocumentViewer(id, title, fileUrl, files);
    DocumentViewer.activeViewers[id] = viewer;
    return viewer;
  }

  constructor(id, title, fileUrl, files = []) {
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
    this.translateX = 100;
    this.translateY = 100;
    Object.assign(this.viewer.style, {
      position: "fixed",
      left: `${this.translateX}px`,
      top: `${this.translateY}px`,
      width: "720px",
      height: "560px",
      background: "#ffffff",
      borderRadius: "10px",
      border: "1px solid #ddd",
      boxShadow: "0 8px 24px rgba(0, 0, 0, 0.15)",
      zIndex: 10000,
      display: "flex",
      flexDirection: "column",
      resize: "both",
      overflow: "hidden",
      boxSizing: "border-box",
      fontFamily: "Segoe UI, Roboto, sans-serif"
    });

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
      background: "#fafafa"
    });

    this.viewer.append(this.header, this.toolbar, this.content);
    document.body.appendChild(this.viewer);

    this.addDragFunctionality();
    this.addStyleFixes();
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
    gap: "12px"
  });

  const span = document.createElement("span");
  span.innerText = this.title;
  span.style.flexShrink = "0";

  this.navContainer = document.createElement("div");
  Object.assign(this.navContainer.style, {
    display: "flex",
    gap: "6px",
    flexWrap: "wrap",
    flex: "1"
  });

  if (this.files.length > 0) this.createNavigationButtons();

  this.closeBtn = document.createElement("span");
  this.closeBtn.innerHTML = "&times;";
  Object.assign(this.closeBtn.style, {
    cursor: "pointer",
    fontSize: "22px",
    userSelect: "none",
    padding: "0 6px",
    flexShrink: "0"
  });
  this.closeBtn.onmouseover = () => (this.closeBtn.style.color = "#ff4d4d");
  this.closeBtn.onmouseout = () => (this.closeBtn.style.color = "#fff");
  this.closeBtn.onclick = e => {
    e.stopPropagation();
    this.remove();
  };

  this.header.append(span, this.navContainer, this.closeBtn);
}


  createNavigationButtons() {
    const currentOp = this.title.match(/\(P(\w+)\)/)?.[1];
    this.navContainer.innerHTML = "";
    this.files.sort((a,b) => a.operator - b.operator)
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
          borderRadius: "4px"
        });
        if (operator == currentOp) {
          btn.style.boxShadow = "0 0 0 2px rgba(13,110,253,0.5)";
        }
        btn.onclick = e => {
          e.stopPropagation();
          [...this.navContainer.children].forEach(b => {
            const isActive = b.dataset.operator == operator;
            b.style.background = isActive ? "#0d6efd" : "#333";
            b.style.borderColor = isActive ? "#0d6efd" : "#ccc";
            b.style.boxShadow = isActive ? "0 0 0 2px rgba(13,110,253,0.5)" : "";
          });
          DocumentViewer.create("viewerWI", `Work Instruction (P${operator})`, url, this.files);
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
      fontSize: "14px"
    });
    document.body.appendChild(this.toolbar); // ensure always top layer
    setTimeout(() => {
      this.toolbar.querySelectorAll("button").forEach(btn => {
        Object.assign(btn.style, {
          padding: "4px 10px",
          border: "1px solid #ccc",
          borderRadius: "4px",
          background: "#fff",
          cursor: "pointer"
        });
      });
    }, 0);
  }

  loadFile() {
    if (this.isPDF) return this.loadPDF();
    this.loadImage();
  }

  loadImage() {
    const img = document.createElement("img");
    img.src = this.fileUrl;
    Object.assign(img.style, {
      maxWidth: "100%",
      maxHeight: "100%",
      transform: `scale(${this.scale})`,
      transition: "transform 0.2s ease-out",
      transformOrigin: "center top"
    });
    this.targetElement = img;
    this.content.appendChild(img);
    this.addZoomListeners();
  }

  loadPDF() {
    pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js";
    pdfjsLib.getDocument(this.fileUrl).promise.then(pdf => {
      this.pdfDoc = pdf;
      this.currentPage = 1;
      this.renderPDF();
      new ResizeObserver(() => this.renderPDF()).observe(this.content);

      const t = this.toolbar;
      t.querySelector("#zoomOut").onclick = () => this.applyZoom(this.scale - 0.1);
      t.querySelector("#zoomIn").onclick = () => this.applyZoom(this.scale + 0.1);
      t.querySelector("#resetZoom").onclick = () => this.applyZoom(1);
      t.querySelector("#fitZoom").onclick = () => { this.scale = 1; this.renderPDF(); };
      t.querySelector("#prevPage").onclick = () => { if (this.currentPage > 1) {this.currentPage--; this.renderPDF();}};
      t.querySelector("#nextPage").onclick = () => { if (this.currentPage < pdf.numPages) {this.currentPage++; this.renderPDF();}};
      t.querySelector("#downloadFile").onclick = () => {
        const a = document.createElement("a");
        a.href = this.fileUrl;
        a.download = this.fileUrl.split("/").pop();
        a.click();
      };
      this.addZoomListeners();
    });
  }

  addZoomListeners() {
    this.content.onwheel = e => {
      e.preventDefault();
      this.applyZoom(this.scale + (e.deltaY < 0 ? 0.1 : -0.1));
    };
    new Hammer(this.content).get("pinch").set({ enable: true })
      .on("pinch", e => this.applyZoom(this.scale * e.scale));
  }

  renderPDF() {
    if (!this.pdfDoc) return;
    if (this.renderTask) this.renderTask.cancel();
    this.pdfDoc.getPage(this.currentPage).then(page => {
      const base = page.getViewport({ scale: 1 });
      const fit = Math.min(this.content.clientWidth / base.width, this.content.clientHeight / base.height);
      const viewport = page.getViewport({ scale: fit * this.scale });
      let canvas = this.targetElement || document.createElement("canvas");
      Object.assign(canvas, { width: viewport.width, height: viewport.height });
      if (!this.targetElement) {
        this.content.innerHTML = "";
        this.content.appendChild(canvas);
        this.targetElement = canvas;
      }
      this.renderTask = page.render({ canvasContext: canvas.getContext("2d"), viewport });
      this.renderTask.promise.catch(err => {
        if (err.name !== "RenderingCancelledException") console.error(err);
      });
      this.toolbar.querySelector("#pageInfo").innerText = `Page ${this.currentPage} / ${this.pdfDoc.numPages}`;
    });
  }

  applyZoom = debounce(newScale => {
    this.scale = Math.max(0.3, Math.min(4, newScale));
    if (this.isImage) {
      this.targetElement.style.transform = `scale(${this.scale})`;
    } else {
      this.renderPDF();
    }
  });

  addDragFunctionality() {
    this.header.onmousedown = e => {
      this.isDragging = true;
      this.dragStartX = e.clientX - this.translateX;
      this.dragStartY = e.clientY - this.translateY;
      document.onmousemove = this.handleDrag;
      document.onmouseup = this.stopDrag;
    };
  }

  handleDrag = e => {
    if (!this.isDragging) return;
    this.translateX = e.clientX - this.dragStartX;
    this.translateY = e.clientY - this.dragStartY;
    Object.assign(this.viewer.style, {
      left: `${this.translateX}px`,
      top: `${this.translateY}px`
    });
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
    if (this.id === "viewerWI" && Object.keys(DocumentViewer.activeViewers).length === 0) {
      document.getElementById("wi-nav-header")?.remove();
    }
  }
}

// Bind buttons
document.getElementById("btnDrawing")?.addEventListener("click", function() {
  const f = this.dataset.file;
  if (f) DocumentViewer.create("viewerDrawing", "Drawing", f);
});

document.getElementById("btnWorkInstruction")?.addEventListener("click", async function() {
  const hostnameId = new URLSearchParams(window.location.search).get("hostname_id");
  try {
    const res = await fetch(`../controller/dor-documents-viewer.php?hostname_id=${hostnameId}&json=1`);
    const files = (await res.json()).files || [];
    if (!files.length) return alert("No work instructions found.");

    const headerId = "wi-nav-header";
    document.getElementById(headerId)?.remove();

    const navBar = document.createElement("div");
    navBar.id = headerId;

    const title = document.createElement("span");
    title.textContent = "Work Instruction"; title.style.fontWeight = "bold";
    navBar.append(title);

    files.sort((a,b) => a.operator - b.operator).forEach(f => {
      const b = document.createElement("button");
      b.textContent = `P${f.operator}`;
      b.dataset.operator = f.operator;
      Object.assign(b.style, {
        background: "#333", color: "#fff",
        border: "1px solid #ccc",
        padding: "4px 8px",
        cursor: "pointer",
        borderRadius: "4px"
      });
      b.onclick = () => {
        navBar.querySelectorAll("button[data-operator]").forEach(x => {
          const isAct = x.dataset.operator === b.dataset.operator;
          x.style.background = isAct ? "#0d6efd" : "#333";
          x.style.borderColor = isAct ? "#0d6efd" : "#ccc";
          x.style.boxShadow = isAct ? "0 0 0 2px rgba(13,110,253,0.5)" : "";
        });
        DocumentViewer.create("viewerWI", `Work Instruction (P${f.operator})`, f.url, files);
      };
      navBar.append(b);
    });

    const closeBtn = document.createElement("button");
    closeBtn.textContent = "✖";
    Object.assign(closeBtn.style, {
      marginLeft: "auto", padding: "4px 8px", cursor: "pointer"
    });
    closeBtn.onclick = () => {
      navBar.remove();
      DocumentViewer.activeViewers["viewerWI"]?.remove();
    };
    navBar.append(closeBtn);

    document.body.prepend(navBar);
    navBar.querySelector("button[data-operator]")?.click();
  } catch (err) {
    console.error(err);
    alert("Failed to load work instructions.");
  }
});

document.getElementById("btnPrepCard")?.addEventListener("click", function() {
  const f = this.dataset.file;
  if (f) DocumentViewer.create("viewerPrepCard", "Preparation Card", f);
});
