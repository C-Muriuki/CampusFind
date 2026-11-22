document.addEventListener("DOMContentLoaded", () => {
    const imageInput = document.getElementById("item_image");
    const previewContainer = document.getElementById("image-preview");
    const previewImage = document.getElementById("preview-image");
    const removeButton = document.getElementById("remove-image");

    if (!imageInput || !previewContainer || !previewImage) {
        return;
    }

    let currentObjectUrl = null;

    imageInput.addEventListener("change", () => {
        const file = imageInput.files?.[0];

        if (!file) {
            clearPreview();
            return;
        }

        const allowedTypes = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        if (!allowedTypes.includes(file.type)) {
            alert("Please select a JPG, PNG or WebP image.");
            imageInput.value = "";
            clearPreview();
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert("The image must not exceed 5 MB.");
            imageInput.value = "";
            clearPreview();
            return;
        }

        clearPreview();

        currentObjectUrl = URL.createObjectURL(file);
        previewImage.src = currentObjectUrl;
        previewContainer.hidden = false;
    });

    function clearPreview() {
        if (currentObjectUrl) {
            URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }

        previewImage.removeAttribute("src");
        previewContainer.hidden = true;
    }

    if (removeButton) {
        removeButton.addEventListener("click", () => {
            imageInput.value = "";
            clearPreview();
        });
    }
});

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("form[data-confirm]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            const message = form.dataset.confirm || "Are you sure you want to continue?";
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});