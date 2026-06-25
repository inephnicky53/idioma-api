document.addEventListener('DOMContentLoaded', function() {
    const uploadContainer = document.querySelector('.vimeo-upload-container');
    const uriField = document.querySelector('.vimeo-uri-field');
    const progressContainer = document.getElementById('vimeo-upload-progress-container');
    const progressBar = document.getElementById('vimeo-upload-progress-bar');
    const statusText = document.getElementById('vimeo-upload-status-text');
    const fileInput = document.getElementById('vimeo-file-input');
    const uploadButton = document.getElementById('vimeo-upload-button');

    if (!uploadContainer || !uriField || !fileInput || !uploadButton) return;

    uploadButton.addEventListener('click', function(e) {
        e.preventDefault();
        fileInput.click();
    });

    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('video', file);
        formData.append('title', document.querySelector('input[name="CourseVideo[title]"]').value || 'Video');
        formData.append('description', document.querySelector('textarea[name="CourseVideo[description]"]').value || '');

        // Show progress container
        if (progressContainer) progressContainer.style.display = 'block';
        if (progressBar) {
            progressBar.style.width = '0%';
            progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        }
        if (statusText) {
            statusText.innerText = 'Upload en cours...';
            statusText.className = 'text-muted d-block mt-2';
        }

        // Disable upload button
        uploadButton.disabled = true;
        const originalContent = uploadButton.innerHTML;
        uploadButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Upload...';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/admin/vimeo/upload', true);

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable && progressBar) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
            }
        });

        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                const data = JSON.parse(xhr.responseText);
                if (data.url) {
                    let uri = data.url;
                    // Convert to /videos/XXXXX format if needed
                    if (uri.startsWith('https://vimeo.com/')) {
                        uri = '/videos/' + uri.split('/').pop();
                    }
                    uriField.value = uri;

                    if (progressBar) {
                        progressBar.style.width = '100%';
                        progressBar.className = 'progress-bar bg-success';
                    }
                    if (statusText) {
                        statusText.innerText = "Vidéo prête ! N'oubliez pas d'enregistrer le formulaire.";
                        statusText.className = 'text-success font-weight-bold d-block mt-2';
                    }

                    // Hide local file upload
                    const localUpload = document.getElementById('CourseVideo_videoFileUpload');
                    if (localUpload) {
                        const formGroup = localUpload.closest('.form-group') || localUpload.closest('.mb-3');
                        if (formGroup) formGroup.style.display = 'none';
                    }
                } else {
                    if (statusText) {
                        statusText.innerText = data.error || 'Erreur lors de l\'upload';
                        statusText.className = 'text-danger font-weight-bold d-block mt-2';
                    }
                }
            } else {
                const data = JSON.parse(xhr.responseText);
                if (statusText) {
                    statusText.innerText = data.error || 'Erreur lors de l\'upload';
                    statusText.className = 'text-danger font-weight-bold d-block mt-2';
                }
            }
            uploadButton.disabled = false;
            uploadButton.innerHTML = originalContent;
        });

        xhr.addEventListener('error', function() {
            if (statusText) {
                statusText.innerText = 'Erreur réseau';
                statusText.className = 'text-danger font-weight-bold d-block mt-2';
            }
            uploadButton.disabled = false;
            uploadButton.innerHTML = originalContent;
        });

        xhr.send(formData);
    });
});
