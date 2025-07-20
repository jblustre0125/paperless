function debounce(func, delay = 50) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

function createViewer(id, title, fileUrl) {
    if (document.getElementById(id)) return;

    const ext = fileUrl.split('.').pop().toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(ext);
    const isPDF = ext === 'pdf';

    if (!isImage && !isPDF) {
        alert('Unsupported file type: ' + ext);
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';

    const viewer = document.createElement('div');
    viewer.id = id;
    let translateX = 100, translateY = 100;
    Object.assign(viewer.style, {
        position: 'fixed',
        transform: `translate(${translateX}px, ${translateY}px)`,
        width: '720px',
        height: '560px',
        background: '#ffffff',
        borderRadius: '10px',
        border: '1px solid #ddd',
        boxShadow: '0 8px 24px rgba(0, 0, 0, 0.15)',
        zIndex: 9999,
        display: 'flex',
        flexDirection: 'column',
        resize: 'both',
        overflow: 'hidden',
        boxSizing: 'border-box',
        fontFamily: 'Segoe UI, Roboto, sans-serif',
    });

    const header = document.createElement('div');
    Object.assign(header.style, {
        background: '#1e1e2f',
        color: '#fff',
        padding: '10px 16px',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        cursor: 'grab',
        fontSize: '16px',
        fontWeight: '500',
        borderTopLeftRadius: '10px',
        borderTopRightRadius: '10px',
        userSelect: 'none'
    });

    const titleSpan = document.createElement('span');
    titleSpan.innerText = title;

    const closeBtn = document.createElement('span');
    closeBtn.innerHTML = '&times;';
    Object.assign(closeBtn.style, {
        cursor: 'pointer',
        fontSize: '22px',
        padding: '2px 6px',
        borderRadius: '4px',
        transition: 'background 0.2s ease-in-out',
    });
    closeBtn.onmouseover = () => closeBtn.style.background = '#ff4d4d';
    closeBtn.onmouseout = () => closeBtn.style.background = 'transparent';
    closeBtn.onclick = () => viewer.remove();

    header.appendChild(titleSpan);
    header.appendChild(closeBtn);

    const toolbar = document.createElement('div');
    toolbar.innerHTML = `
        <button id="prevPage">◀️</button>
        <span id="pageInfo">Page 1</span>
        <button id="nextPage">▶️</button>
        <button id="zoomOut">−</button>
        <button id="zoomIn">＋</button>
        <button id="resetZoom">Reset</button>
        <button id="fitZoom">Fit</button>
        <button id="downloadFile">📥</button>
    `;
    Object.assign(toolbar.style, {
        background: '#f2f2f5',
        padding: '8px 12px',
        display: isPDF ? 'flex' : 'none',
        gap: '10px',
        alignItems: 'center',
        borderBottom: '1px solid #ccc',
        fontSize: '14px',
        flexWrap: 'wrap',
    });

    setTimeout(() => {
        toolbar.querySelectorAll('button').forEach(btn => {
            Object.assign(btn.style, {
                padding: '6px 12px',
                fontSize: '14px',
                border: '1px solid #ccc',
                borderRadius: '6px',
                background: '#ffffff',
                cursor: 'pointer',
                transition: 'all 0.2s ease',
            });
            btn.onmouseover = () => btn.style.background = '#e8e8f0';
            btn.onmouseout = () => btn.style.background = '#ffffff';
        });
    }, 0);

    const content = document.createElement('div');
    Object.assign(content.style, {
        flex: 1,
        position: 'relative',
        overflow: 'hidden',
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        background: '#fafafa',
    });

    viewer.appendChild(header);
    viewer.appendChild(toolbar);
    viewer.appendChild(content);
    document.body.appendChild(viewer);

    let scale = 1.0;
    let renderTask = null;
    let targetElement = null;
    let pdfDoc = null;
    let currentPage = 1;

    const renderPDF = () => {
        if (!pdfDoc) return;
        if (renderTask) renderTask.cancel();

        pdfDoc.getPage(currentPage).then((page) => {
            const baseViewport = page.getViewport({ scale: 1 });
            const fitScale = Math.min(content.clientWidth / baseViewport.width, content.clientHeight / baseViewport.height);
            const finalScale = fitScale * scale;
            const viewport = page.getViewport({ scale: finalScale });

            const canvas = targetElement || document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = viewport.width;
            canvas.height = viewport.height;

            if (!targetElement) {
                content.innerHTML = '';
                content.appendChild(canvas);
                targetElement = canvas;
            }

            renderTask = page.render({ canvasContext: ctx, viewport });
            renderTask.promise.catch(err => {
                if (err.name !== 'RenderingCancelledException') console.error('PDF Render error:', err);
            });

            document.getElementById('pageInfo').innerText = `Page ${currentPage} / ${pdfDoc.numPages}`;
        });
    };

    const applyZoom = debounce((newScale) => {
        scale = Math.min(Math.max(newScale, 0.3), 4);
        if (isImage && targetElement) {
            targetElement.style.transform = `scale(${scale})`;
        } else if (isPDF) {
            renderPDF();
        }
    });

    if (isImage) {
        const img = document.createElement('img');
        img.src = fileUrl;
        Object.assign(img.style, {
            maxWidth: '100%',
            maxHeight: '100%',
            objectFit: 'contain',
            transformOrigin: 'center center',
            transform: `scale(${scale})`,
            transition: 'transform 0.2s ease-out',
        });
        targetElement = img;
        content.appendChild(img);

        content.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            applyZoom(scale + delta);
        });

        const hammer = new Hammer(content);
        hammer.get('pinch').set({ enable: true });
        hammer.on('pinch', (e) => applyZoom(scale * e.scale));
    }

    if (isPDF) {
        pdfjsLib.getDocument(fileUrl).promise.then((pdf) => {
            pdfDoc = pdf;
            currentPage = 1;
            renderPDF();

            const resizeObserver = new ResizeObserver(() => renderPDF());
            resizeObserver.observe(content);

            toolbar.querySelector('#zoomIn').onclick = () => applyZoom(scale + 0.1);
            toolbar.querySelector('#zoomOut').onclick = () => applyZoom(scale - 0.1);
            toolbar.querySelector('#resetZoom').onclick = () => applyZoom(1.0);
            toolbar.querySelector('#fitZoom').onclick = () => { scale = 1.0; renderPDF(); };
            toolbar.querySelector('#prevPage').onclick = () => {
                if (currentPage > 1) { currentPage--; renderPDF(); }
            };
            toolbar.querySelector('#nextPage').onclick = () => {
                if (currentPage < pdfDoc.numPages) { currentPage++; renderPDF(); }
            };
            toolbar.querySelector('#downloadFile').onclick = () => {
                const a = document.createElement('a');
                a.href = fileUrl;
                a.download = fileUrl.split('/').pop();
                a.click();
            };

            content.addEventListener('wheel', (e) => {
                e.preventDefault();
                const delta = e.deltaY > 0 ? -0.1 : 0.1;
                applyZoom(scale + delta);
            });

            const hammer = new Hammer(content);
            hammer.get('pinch').set({ enable: true });
            hammer.on('pinch', (e) => applyZoom(scale * e.scale));
        });
    }

    let startX = 0, startY = 0;
    header.addEventListener('pointerdown', (e) => {
        if (e.target === closeBtn) return;
        startX = e.clientX;
        startY = e.clientY;
        header.setPointerCapture(e.pointerId);

        const move = (ev) => {
            const dx = ev.clientX - startX;
            const dy = ev.clientY - startY;
            translateX += dx;
            translateY += dy;
            viewer.style.transform = `translate(${translateX}px, ${translateY}px)`;
            startX = ev.clientX;
            startY = ev.clientY;
        };
        const up = (ev) => {
            header.removeEventListener('pointermove', move);
            header.removeEventListener('pointerup', up);
            header.releasePointerCapture(ev.pointerId);
        };

        header.addEventListener('pointermove', move);
        header.addEventListener('pointerup', up);
    });

    // Resize viewer with pinch gesture
    const hammerViewer = new Hammer(viewer);
    hammerViewer.get('pinch').set({ enable: true });
    let baseWidth = viewer.offsetWidth;
    let baseHeight = viewer.offsetHeight;

    hammerViewer.on('pinchstart', () => {
        baseWidth = viewer.offsetWidth;
        baseHeight = viewer.offsetHeight;
    });

    hammerViewer.on('pinchmove', (e) => {
        const newWidth = Math.max(320, baseWidth * e.scale);
        const newHeight = Math.max(240, baseHeight * e.scale);
        viewer.style.width = `${newWidth}px`;
        viewer.style.height = `${newHeight}px`;
        if (isPDF) renderPDF();
    });
}

// Button bindings
document.getElementById('btnDrawing')?.addEventListener('click', function () {
    const file = this.dataset.file;
    if (file) createViewer('viewerDrawing', 'Drawing', file);
});
document.getElementById('btnWorkInstruction')?.addEventListener('click', function () {
    const file = this.dataset.file;
    if (file) createViewer('viewerWI', 'Work Instruction', file);
});
document.getElementById('btnPrepCard')?.addEventListener('click', function () {
    const file = this.dataset.file;
    if (file) createViewer('viewerPrepCard', 'Preparation Card', file);
});
