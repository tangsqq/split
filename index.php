<?php

/**
 * 后端处理逻辑：处理 Excel 转换请求
 */
if (isset($_FILES['excel_file'])) {
    header('Content-Type: application/json');
    
    // 1. 设置临时目录 (Linux/Windows 通用)
    $uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_tool_';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $file = $_FILES['excel_file'];
    // 过滤掉文件名中的特殊字符，防止命令注入
    $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $tmpFilePath = $uploadDir . DIRECTORY_SEPARATOR . uniqid() . '_' . $safeFileName;
    
    move_uploaded_file($file['tmp_name'], $tmpFilePath);

    // 2. 自动识别系统环境并设置命令
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // --- 本地 Windows 环境 ---
        $sofficePath = '"C:\Program Files\LibreOffice\program\soffice.exe"';
        $cmd = "$sofficePath --headless --convert-to pdf --outdir " . escapeshellarg($uploadDir) . " " . escapeshellarg($tmpFilePath);
    } else {
        // --- Render / Docker (Linux) 环境 ---
        // 关键点：export HOME=/tmp 解决了 Linux 无家目录权限问题；2>&1 用于捕获详细错误
        $cmd = "export HOME=/tmp && libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($uploadDir) . " " . escapeshellarg($tmpFilePath) . " 2>&1";
    }

    exec($cmd, $output, $returnVar);

    // 3. 处理转换结果
    if ($returnVar === 0) {
        // LibreOffice 默认会将原后缀替换为 .pdf
        $pathInfo = pathinfo($tmpFilePath);
        $pdfPath = $uploadDir . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '.pdf';

        if (file_exists($pdfPath)) {
            echo json_encode([
                'success' => true,
                'pdf_base64' => base64_encode(file_get_contents($pdfPath)),
                'filename' => $file['name']
            ]);
            @unlink($pdfPath); // 发送后立即清理
        } else {
            echo json_encode(['success' => false, 'error' => 'PDF generated but not found', 'debug' => $pdfPath]);
        }
    } else {
        // 如果失败，返回详细日志
        echo json_encode([
            'success' => false, 
            'error' => 'LibreOffice conversion failed', 
            'details' => $output,
            'os' => PHP_OS
        ]);
    }

    @unlink($tmpFilePath); // 清理原始上传文件
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📑</text></svg>">
    <title>PDF Reorder, Rotate & Split</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --split: #f87171;
            --bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 20px 20px;
            margin: 0;
            padding: 40px 20px;
            color: #1e293b;
        }

        .setup-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            text-align: center;
            max-width: 800px;
            margin: 0 auto 40px;
            border: 1px solid white;
        }

        .setup-card h2 {
            margin: 0 0 10px 0;
            font-weight: 800;
            letter-spacing: -0.025em;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .setup-card p {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 25px;
        }

        .btn {
            padding: 10px 24px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 42px;
            box-sizing: border-box;
        }

        .btn-main {
            background: black;
            color: white;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-main:hover {
            background: #334155;
            transform: translateY(-1px);
        }

        .btn-clear {
            background: #fff;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
        }

        .btn-clear:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .file-label {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: #94a3b8;
            margin-top: 12px;
            padding: 0 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #file-selector {
            display: none;
        }

        .file-upload-label {
            padding: 10px 24px;
            border-radius: 20px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 42px;
            box-sizing: border-box;
        }

        .workspace-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 30px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 25px;
            border: 2px dashed #e2e8f0;
            min-height: 300px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .segment-header {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 15px 25px;
            border-radius: 18px;
            margin-top: 15px;
            border: 1px solid #e2e8f0;
        }

        .rename-input {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 8px 15px;
            font-size: 14px;
            flex-grow: 1;
            max-width: 400px;
            outline: none;
        }

        .page-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 12px;
            cursor: grab;
            position: relative;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            user-select: none;
        }

        .page-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
        }

        .page-card.dragging {
            opacity: 0.5;
        }

        .page-card.drag-over {
            border-left: 4px solid var(--primary);
        }

        canvas {
            width: 100%;
            height: auto;
            border-radius: 20px;
            display: block;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: #f8fafc;
        }

        .badge {
            position: absolute;
            top: -12px;
            left: 12px;
            background: #1e293b;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            z-index: 5;
        }

        .rotate-btn {
            position: absolute;
            bottom: 35px;
            right: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            background: white;
        }

        .delete-btn {
            position: absolute;
            top: -12px;
            right: 12px;
            background: var(--split);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 15;
        }

        .page-card:hover .delete-btn {
            display: flex;
        }

        .page-card.split-active {
            border-right: 4px dashed var(--split);
            margin-right: 5px;
        }

        .page-card.split-active::after {
            content: '✂️';
            position: absolute;
            right: -16px;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            padding: 2px;
        }

        #loading {
            display: none;
            margin-left: 15px;
            color: var(--primary);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.5;
            }

            50% {
                opacity: 1;
            }
        }

        .modal-close-btn {
            margin-top: 20px;
            padding: 10px 20px;
            background: black;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-content {
            background: white;
            padding: 32px;
            border-radius: 25px;
            text-align: center;
            max-width: 360px;
            width: 90%;
        }

        #previewModal {
            background: rgba(0, 0, 0, 0.9);
        }

        #previewImage {
            max-width: 95%;
            max-height: 95vh;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.8);
        }

        .home-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            color: #1e293b;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            z-index: 10000;
        }

        .home-btn i {
            font-size: 40px;
        }
    </style>
</head>

<body>
    <div class="setup-card">
        <h2>PDF Reorder, Rotate & Split</h2>
        <p>Support PDF/Excel | Right-Click: Split | Left-Click: Preview | Drag: Reorder</p>

        <div style="display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap;">
            <label for="file-selector" class="file-upload-label">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Choose PDF/Excel
            </label>
            <input type="file" id="file-selector" accept="application/pdf, .xlsx, .xls" multiple>
            <button class="btn btn-main" onclick="exportPDF()">Export</button>
            <button class="btn btn-clear" onclick="location.reload()">Clear All</button>
            <span id="loading">Converting to PDF...</span>
        </div>
    </div>

    <div id="workspace" class="workspace-grid"></div>

    <div id="customAlert" class="modal-overlay">
        <div class="modal-content">
            <h3 id="alertTitle">Status</h3>
            <p id="alertMessage"></p>
            <button class="modal-close-btn" id="alertBtn" onclick="closeAlert()">OK</button>
        </div>
    </div>

    <div id="previewModal" class="modal-overlay" onclick="closePreview()">
        <img id="previewImage" src="">
    </div>

    <a href="index.html" class="home-btn" title="Back to Home">
        <i class="fa fa-home"></i>
    </a>

    <script>
        const {
            PDFDocument,
            degrees
        } = PDFLib;
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';

        let sourcePdfs = new Map();
        let state = {
            pageOrder: [],
            splits: new Set(),
            segmentNames: {}
        };

        // 核心修复函数：深度复制 Buffer，防止被 Detach
        function cloneBuffer(buffer) {
            const dst = new ArrayBuffer(buffer.byteLength);
            new Uint8Array(dst).set(new Uint8Array(buffer));
            return dst;
        }

        document.getElementById('file-selector').addEventListener('change', async (e) => {
            const files = Array.from(e.target.files);
            if (files.length === 0) return;

            document.getElementById('alertBtn').style.display = 'none';
            showAlert("Converting to PDF...");

            for (const file of files) {
                const fileId = crypto.randomUUID();
                try {
                    let rawBuffer;
                    if (file.name.match(/\.(xlsx|xls)$/i)) {
                        const formData = new FormData();
                        formData.append('excel_file', file);
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (!result.success) throw new Error(result.error);

                        const binaryStr = atob(result.pdf_base64);
                        const bytes = new Uint8Array(binaryStr.length);
                        for (let i = 0; i < binaryStr.length; i++) bytes[i] = binaryStr.charCodeAt(i);
                        rawBuffer = bytes.buffer;
                    } else {
                        rawBuffer = await file.arrayBuffer();
                    }

                    // 1. 先存入原始数据的物理拷贝
                    sourcePdfs.set(fileId, {
                        buffer: cloneBuffer(rawBuffer),
                        pdfjsDoc: null
                    });

                    // 2. 用另一个物理拷贝去给 PDF.js 渲染（渲染后这个 buffer 可能会被分离）
                    const renderBuffer = cloneBuffer(rawBuffer);
                    const pdfjsDoc = await pdfjsLib.getDocument({
                        data: renderBuffer
                    }).promise;
                    sourcePdfs.get(fileId).pdfjsDoc = pdfjsDoc;

                    for (let i = 0; i < pdfjsDoc.numPages; i++) {
                        state.pageOrder.push({
                            fileId: fileId,
                            originalIdx: i,
                            rotation: 0,
                            fileName: file.name
                        });
                    }
                } catch (err) {
                    console.error(err);
                    alert("Error: " + err.message);
                }
            }
            document.getElementById('alertBtn').style.display = 'inline-block';
            closeAlert();
            renderUI();
        });

        function renderUI() {
            const container = document.getElementById('workspace');
            container.innerHTML = '';
            let segmentIndex = 0;

            const createRenameBar = (idx) => {
                const div = document.createElement('div');
                div.className = 'segment-header';
                const defaultName = `Document_Part_${idx + 1}`;
                div.innerHTML = `
                    <span class="segment-label">File ${idx + 1}:</span>
                    <input type="text" class="rename-input" value="${state.segmentNames[idx] || defaultName}" oninput="state.segmentNames[${idx}] = this.value">
                `;
                return div;
            };

            if (state.pageOrder.length > 0) container.appendChild(createRenameBar(segmentIndex++));

            state.pageOrder.forEach((pageObj, currentPos) => {
                const card = document.createElement('div');
                card.className = `page-card ${state.splits.has(currentPos) ? 'split-active' : ''}`;
                card.draggable = true;
                const canvasId = `canvas-${pageObj.fileId}-${pageObj.originalIdx}-${currentPos}`;

                card.innerHTML = `
                    <div class="badge">#${currentPos + 1}</div>
                    <button class="delete-btn" title="Delete Page"><i class="fa fa-times"></i></button>
                    <canvas id="${canvasId}"></canvas>
                    <button class="rotate-btn" title="Rotate 90°"><i class="fa fa-rotate-right"></i></button>
                    <div class="file-label" title="${pageObj.fileName}">${pageObj.fileName}</div>
                `;

                card.onclick = () => showPreview(card.querySelector('canvas'));
                card.oncontextmenu = (e) => {
                    e.preventDefault();
                    if (currentPos === state.pageOrder.length - 1) return;
                    state.splits.has(currentPos) ? state.splits.delete(currentPos) : state.splits.add(currentPos);
                    renderUI();
                };

                card.querySelector('.rotate-btn').onclick = (e) => {
                    e.stopPropagation();
                    pageObj.rotation = (pageObj.rotation + 90) % 360;
                    renderUI();
                };

                card.querySelector('.delete-btn').onclick = (e) => {
                    e.stopPropagation();
                    state.pageOrder.splice(currentPos, 1);
                    renderUI();
                };

                card.ondragstart = (e) => {
                    e.dataTransfer.setData('text/plain', currentPos);
                    card.classList.add('dragging');
                };
                card.ondragover = (e) => {
                    e.preventDefault();
                    card.classList.add('drag-over');
                };
                card.ondragleave = () => card.classList.remove('drag-over');
                card.ondrop = (e) => {
                    const fromPos = parseInt(e.dataTransfer.getData('text/plain'));
                    const item = state.pageOrder.splice(fromPos, 1)[0];
                    state.pageOrder.splice(currentPos, 0, item);
                    renderUI();
                };

                container.appendChild(card);
                drawThumb(pageObj, canvasId);
                if (state.splits.has(currentPos)) container.appendChild(createRenameBar(segmentIndex++));
            });
        }

        async function drawThumb(pageObj, canvasId) {
            try {
                const source = sourcePdfs.get(pageObj.fileId);
                const page = await source.pdfjsDoc.getPage(pageObj.originalIdx + 1);
                const viewport = page.getViewport({
                    scale: 2.0
                });
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvas.style.transform = `rotate(${pageObj.rotation}deg)`;
                await page.render({
                    canvasContext: ctx,
                    viewport
                }).promise;
            } catch (e) {
                console.error(e);
            }
        }

        async function exportPDF() {
            if (state.pageOrder.length === 0) return showAlert("No pages to export.");
            document.getElementById('alertBtn').style.display = 'none';
            showAlert("Exporting... Please do not close.");

            try {
                // 每次导出都创建全新的 PDFDocument 副本
                const pdfLibDocs = new Map();
                for (let [id, source] of sourcePdfs) {
                    const doc = await PDFDocument.load(cloneBuffer(source.buffer));
                    pdfLibDocs.set(id, doc);
                }

                const sortedSplits = Array.from(state.splits).sort((a, b) => a - b);
                let start = 0;
                const groups = [];
                for (const point of sortedSplits) {
                    groups.push(state.pageOrder.slice(start, point + 1));
                    start = point + 1;
                }
                groups.push(state.pageOrder.slice(start));

                for (let i = 0; i < groups.length; i++) {
                    const newDoc = await PDFDocument.create();
                    for (const pageObj of groups[i]) {
                        const srcDoc = pdfLibDocs.get(pageObj.fileId);
                        const [copiedPage] = await newDoc.copyPages(srcDoc, [pageObj.originalIdx]);
                        if (pageObj.rotation !== 0) copiedPage.setRotation(degrees(pageObj.rotation));
                        newDoc.addPage(copiedPage);
                    }
                    const bytes = await newDoc.save();
                    const blob = new Blob([bytes], {
                        type: 'application/pdf'
                    });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    let name = state.segmentNames[i] || `Document_Part_${i + 1}`;
                    a.download = name.toLowerCase().endsWith('.pdf') ? name : name + '.pdf';
                    a.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                }
                showAlert("Success!");
            } catch (err) {
                console.error(err);
                showAlert("Export Error: " + err.message);
            } finally {
                document.getElementById('alertBtn').style.display = 'inline-block';
            }
        }

        function showAlert(msg) {
            document.getElementById('alertMessage').innerText = msg;
            document.getElementById('customAlert').style.display = 'flex';
        }

        function closeAlert() {
            document.getElementById('customAlert').style.display = 'none';
        }

        function showPreview(canvas) {
            const img = document.getElementById('previewImage');
            img.src = canvas.toDataURL('image/png');
            img.style.transform = canvas.style.transform;
            document.getElementById('previewModal').style.display = 'flex';
        }

        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }
    </script>
</body>

</html>

