document.addEventListener('DOMContentLoaded', function() {
    const uploadButton = document.getElementById('cloudinary-upload-widget');
    const urlField = document.querySelector('.cloudinary-url-field');
    const progressContainer = document.getElementById('upload-progress-container');
    const progressBar = document.getElementById('upload-progress-bar');
    const statusText = document.getElementById('upload-status-text');

    if (!uploadButton || !urlField) return;

    uploadButton.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Upload button clicked');
        
        // Désactiver le bouton pendant la préparation
        const originalContent = uploadButton.innerHTML;
        uploadButton.disabled = true;
        uploadButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Préparation...';

        // 1. Demander une signature au serveur
        console.log('Fetching signature from /admin/cloudinary/signature...');
        fetch('/admin/cloudinary/signature', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                params: {
                    resource_type: 'video'
                }
            })
        })
        .then(response => {
            console.log('Signature response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Signature received data:', data);
            
            // Réactiver le bouton
            uploadButton.disabled = false;
            uploadButton.innerHTML = originalContent;

            // 2. Ouvrir le widget Cloudinary
            const widget = cloudinary.createUploadWidget({
                cloudName: data.cloud_name,
                apiKey: data.api_key,
                uploadSignature: data.signature,
                uploadSignatureTimestamp: data.timestamp,
                uploadPreset: data.upload_preset,
                source: 'uw',
                resourceType: 'auto', // Détection automatique (image ou vidéo)
                multiple: false,
                sources: ['local', 'url'],
                styles: {
                    palette: {
                        window: "#FFFFFF",
                        windowBorder: "#90A0B3",
                        tabIcon: "#3c0366",
                        menuIcons: "#5A616A",
                        textDark: "#000000",
                        textLight: "#FFFFFF",
                        link: "#3c0366",
                        action: "#FF620C",
                        inactiveTabIcon: "#0E2F5A",
                        error: "#F44235",
                        inProgress: "#3c0366",
                        complete: "#20B832",
                        sourceBg: "#E4EBF1"
                    }
                }
            }, (error, result) => {
                if (!error && result && result.event === "success") { 
                    console.log('Done! Here is the video info: ', result.info); 
                    urlField.value = result.info.secure_url;
                    
                    // Masquer le bouton local pour éviter les doubles uploads
                    const localUpload = document.getElementById('CourseVideo_videoFileUpload');
                    if (localUpload) {
                        const formGroup = localUpload.closest('.form-group') || localUpload.closest('.mb-3');
                        if (formGroup) formGroup.style.display = 'none';
                    }

                    // Notification de succès
                    if (progressContainer) progressContainer.style.display = 'block';
                    if (progressBar) {
                        progressBar.style.width = "100%";
                        progressBar.className = "progress-bar bg-success";
                    }
                    if (statusText) {
                        statusText.innerText = "Vidéo prête ! N'oubliez pas d'enregistrer le formulaire.";
                        statusText.className = "text-success font-weight-bold d-block mt-2";
                    }
                }
                
                if (error) {
                    console.error('Upload Widget Error:', error);
                    alert('Erreur lors de l\'upload : ' + error);
                    uploadButton.disabled = false;
                    uploadButton.innerHTML = originalContent;
                }
            });

            widget.open();
        })
        .catch(err => {
            console.error('Error getting signature:', err);
            alert('Erreur de connexion au serveur pour la signature.');
            uploadButton.disabled = false;
            uploadButton.innerHTML = originalContent;
        });
    });
});
