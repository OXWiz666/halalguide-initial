/*
 Simple print and PDF export utility.
 Usage for any button/link:
   <button data-print-target="#printArea" data-doc-title="Halal Application" data-header-title="HalalGuide" data-header-subtitle="Certification System" class="btn btn-sm btn-primary print-trigger">Print</button>
   <button data-pdf-target="#printArea" data-doc-title="Halal Application" class="btn btn-sm btn-secondary pdf-trigger">Save PDF</button>

 The target is any CSS selector for the DOM node you want to print/export.
 Optional data attributes:
 - data-logo: URL to a logo to show in header
 - data-header-title, data-header-subtitle
 - data-doc-title: used as document/file name
*/
(function () {
  function buildHeader(options) {
    const header = document.createElement('div');
    header.style.display = 'flex';
    header.style.alignItems = 'center';
    header.style.justifyContent = 'space-between';
    header.style.borderBottom = '1px solid #e5e7eb';
    header.style.padding = '12px 0';

    const left = document.createElement('div');
    left.style.display = 'flex';
    left.style.alignItems = 'center';
    left.style.gap = '12px';

    if (options.logo) {
      const img = document.createElement('img');
      img.src = options.logo;
      img.style.height = '40px';
      img.style.objectFit = 'contain';
      img.alt = 'Logo';
      left.appendChild(img);
    }

    const titles = document.createElement('div');
    const titleEl = document.createElement('div');
    titleEl.textContent = options.headerTitle || 'HalalGuide';
    titleEl.style.fontSize = '18px';
    titleEl.style.fontWeight = '700';
    titles.appendChild(titleEl);

    if (options.headerSubtitle) {
      const subEl = document.createElement('div');
      subEl.textContent = options.headerSubtitle;
      subEl.style.fontSize = '12px';
      subEl.style.color = '#6b7280';
      titles.appendChild(subEl);
    }

    left.appendChild(titles);

    const right = document.createElement('div');
    right.style.textAlign = 'right';
    right.style.fontSize = '12px';
    right.style.color = '#4b5563';
    right.innerHTML = `
      <div><strong>${options.docTitle || 'Document'}</strong></div>
      <div>${new Date().toLocaleString()}</div>
    `;

    header.appendChild(left);
    header.appendChild(right);
    return header;
  }

  function cloneWithStyles(node) {
    const clone = node.cloneNode(true);
    const container = document.createElement('div');
    container.className = 'print-export-container';
    container.style.fontFamily = '\"Inter\", system-ui, -apple-system, Segoe UI, Roboto, \"Helvetica Neue\", Arial, \"Noto Sans\", \"Apple Color Emoji\", \"Segoe UI Emoji\"';
    container.style.fontSize = '12px';
    container.style.color = '#111827';
    container.style.lineHeight = '1.5';
    container.style.padding = '16px';
    container.appendChild(clone);
    return container;
  }

  function openPrintWindow(contentEl, options) {
    const win = window.open('', '_blank');
    if (!win) return;

    const header = buildHeader(options);
    const wrapper = document.createElement('div');
    wrapper.appendChild(header);
    wrapper.appendChild(contentEl);

    win.document.open();
    win.document.write('<!DOCTYPE html><html><head><title>' + (options.docTitle || 'Document') + '</title>');
    win.document.write('<link rel="stylesheet" href="../assets2/css/print.css">');
    win.document.write('</head><body></body></html>');
    win.document.close();

    win.document.body.appendChild(wrapper);
    setTimeout(function () {
      win.focus();
      win.print();
    }, 250);
  }

  function getOptionsFrom(el) {
    return {
      logo: el.getAttribute('data-logo') || '../assets2/images/ph_halal_logo.png',
      headerTitle: el.getAttribute('data-header-title') || 'HalalGuide',
      headerSubtitle: el.getAttribute('data-header-subtitle') || 'Certification System',
      docTitle: el.getAttribute('data-doc-title') || document.title
    };
  }

  function handlePrintTrigger(e) {
    const targetSel = this.getAttribute('data-print-target');
    if (!targetSel) return;
    const target = document.querySelector(targetSel);
    if (!target) return;
    const cloned = cloneWithStyles(target);
    openPrintWindow(cloned, getOptionsFrom(this));
  }

  function handlePdfTrigger(e) {
    const targetSel = this.getAttribute('data-pdf-target');
    if (!targetSel) return;
    const target = document.querySelector(targetSel);
    if (!target) return;
    const options = getOptionsFrom(this);

    // Default: use native browser print-to-PDF for crisp, selectable text
    const mode = this.getAttribute('data-pdf-mode') || 'print'; // 'print' | 'canvas'
    if (mode === 'print') {
      const cloned = cloneWithStyles(target);
      openPrintWindow(cloned, options); // user can choose "Save as PDF"
      return;
    }

    // Canvas mode fallback – produces rasterized output
    const content = document.createElement('div');
    content.style.background = '#fff';
    content.appendChild(buildHeader(options));
    content.appendChild(cloneWithStyles(target));

    html2canvas(content, { scale: 2, useCORS: true }).then(canvas => {
      const imgData = canvas.toDataURL('image/png');
      const pdf = new jspdf.jsPDF('p', 'pt', 'a4');
      const pageWidth = pdf.internal.pageSize.getWidth();
      const pageHeight = pdf.internal.pageSize.getHeight();
      const imgWidth = pageWidth;
      const imgHeight = canvas.height * (imgWidth / canvas.width);

      let remaining = imgHeight;
      const pageMargin = 36; // better margins
      let y = pageMargin;

      pdf.addImage(imgData, 'PNG', pageMargin, y, imgWidth - pageMargin * 2, imgHeight * ((imgWidth - pageMargin * 2) / imgWidth));
      pdf.save((options.docTitle || 'document') + '.pdf');
    });
  }

  function initPrintExport() {
    document.querySelectorAll('.print-trigger[data-print-target]').forEach(btn => {
      btn.addEventListener('click', handlePrintTrigger);
    });
    document.querySelectorAll('.pdf-trigger[data-pdf-target]').forEach(btn => {
      btn.addEventListener('click', handlePdfTrigger);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPrintExport);
  } else {
    initPrintExport();
  }
})();


