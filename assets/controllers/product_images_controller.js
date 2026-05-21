import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['fileInput', 'dropzone', 'preview', 'message'];
  static values = {
    maxFiles: Number,
    existing: Array,
  };

  connect() {
    this.selectedFiles = [];
    this.renderPreviews();
  }

  handleDragOver(event) {
    event.preventDefault();
    this.dropzoneTarget.classList.add('is-dragover');
  }

  handleDragLeave(event) {
    event.preventDefault();
    if (!this.dropzoneTarget.contains(event.relatedTarget)) {
      this.dropzoneTarget.classList.remove('is-dragover');
    }
  }

  handleDrop(event) {
    event.preventDefault();
    this.dropzoneTarget.classList.remove('is-dragover');
    this.addFiles(event.dataTransfer?.files);
  }

  handleFileSelect(event) {
    this.addFiles(event.target.files);
    event.target.value = '';
  }

  openFileDialog(event) {
    if (event.target?.closest('[data-action="product-images#removeFile"]')) {
      return;
    }
    event.preventDefault();
    this.fileInputTarget.click();
  }

  removeFile(event) {
    event.preventDefault();
    const index = Number(event.currentTarget.dataset.index);
    if (Number.isNaN(index)) {
      return;
    }
    this.selectedFiles.splice(index, 1);
    this.updateInputFiles();
    this.renderPreviews();
    if (this.selectedFiles.length === 0) {
      this.showMessage('No new images selected. Existing gallery will stay unchanged.');
    } else {
      this.showMessage(`${this.selectedFiles.length} image(s) ready to upload.`);
    }
  }

  addFiles(fileList) {
    if (!fileList || !fileList.length) {
      return;
    }

    const availableSlots = this.maxFilesValue
      ? this.maxFilesValue - this.selectedFiles.length
      : 4 - this.selectedFiles.length;

    if (availableSlots <= 0) {
      this.showMessage(`Only ${this.maxFiles()} images can be uploaded at a time.`, true);
      return;
    }

    const incomingFiles = Array.from(fileList).slice(0, availableSlots);
    let invalidFileFound = false;

    incomingFiles.forEach((file) => {
      if (!file.type.startsWith('image/')) {
        invalidFileFound = true;
        return;
      }
      this.selectedFiles.push(file);
    });

    if (invalidFileFound) {
      this.showMessage('Only image files (PNG, JPG, WEBP) are allowed.', true);
    } else {
      this.showMessage(`${this.selectedFiles.length} image(s) ready to upload.`);
    }

    this.updateInputFiles();
    this.renderPreviews();
  }

  updateInputFiles() {
    if (!window.DataTransfer) {
      return;
    }
    const dataTransfer = new DataTransfer();
    this.selectedFiles.forEach((file) => dataTransfer.items.add(file));
    this.fileInputTarget.files = dataTransfer.files;
  }

  renderPreviews() {
    if (!this.hasPreviewTarget) {
      return;
    }

    this.previewTarget.innerHTML = '';

    const existingImages = this.existingGallery();
    if (existingImages.length && this.selectedFiles.length === 0) {
      const existingGroup = this.createGroupHeading('Currently saved images');
      existingImages.forEach((path) => {
        existingGroup.appendChild(this.createExistingPreview(path));
      });
      this.previewTarget.appendChild(existingGroup);
    }

    if (this.selectedFiles.length) {
      const uploadGroup = this.createGroupHeading('Ready to upload');
      this.selectedFiles.forEach((file, index) => {
        uploadGroup.appendChild(this.createUploadedPreview(file, index));
      });
      this.previewTarget.appendChild(uploadGroup);
    }
  }

  createExistingPreview(path) {
    const card = document.createElement('div');
    card.classList.add('image-preview-card', 'image-preview-card--saved');

    const img = document.createElement('img');
    img.src = path.startsWith('http') ? path : this.buildAssetUrl(path);
    img.alt = 'Existing product image';
    card.appendChild(img);

    const label = document.createElement('p');
    label.classList.add('image-preview-card__meta');
    label.textContent = 'Currently live';
    card.appendChild(label);

    return card;
  }

  createUploadedPreview(file, index) {
    const card = document.createElement('div');
    card.classList.add('image-preview-card');

    const img = document.createElement('img');
    const objectUrl = URL.createObjectURL(file);
    img.src = objectUrl;
    img.alt = file.name;
    img.onload = () => URL.revokeObjectURL(objectUrl);
    card.appendChild(img);

    const meta = document.createElement('p');
    meta.classList.add('image-preview-card__meta');
    meta.textContent = file.name;
    card.appendChild(meta);

    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.textContent = 'Remove';
    removeButton.classList.add('image-preview-card__remove');
    removeButton.dataset.index = String(index);
    removeButton.setAttribute('data-action', 'product-images#removeFile');
    card.appendChild(removeButton);

    return card;
  }

  createGroupHeading(label) {
    const group = document.createElement('div');
    group.classList.add('image-preview-group');
    const heading = document.createElement('p');
    heading.classList.add('image-preview-group__title');
    heading.textContent = label;
    group.appendChild(heading);
    return group;
  }

  showMessage(text, isError = false) {
    if (!this.hasMessageTarget) {
      return;
    }
    this.messageTarget.textContent = text;
    if (isError) {
      this.messageTarget.classList.add('text-error');
    } else {
      this.messageTarget.classList.remove('text-error');
    }
  }

  existingGallery() {
    return this.hasExistingValue && Array.isArray(this.existingValue)
      ? this.existingValue
      : [];
  }

  maxFiles() {
    return this.hasMaxFilesValue ? this.maxFilesValue : 4;
  }

  buildAssetUrl(path) {
    if (!path || path.startsWith('http')) {
      return path;
    }
    const base = document.querySelector('base')?.href || window.location.origin;
    return path.startsWith('/')
      ? `${base}${path.replace(/^\//, '')}`
      : `${base.replace(/\/$/, '')}/${path}`;
  }
}

