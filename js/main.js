/**
 * MovieDB — Main JavaScript
 * 
 * This file contains shared JavaScript functionality
 * for the MovieDB website.
 */

// Poster upload preview for admin movie forms
document.addEventListener('DOMContentLoaded', function () {
    const posterUpload = document.getElementById('poster-upload');
    const previewContainer = document.getElementById('poster-preview');
    const placeholderInitial = document.getElementById('placeholder-initial');
    const previewImg = document.getElementById('preview-img');

    if (posterUpload && previewContainer) {
        posterUpload.addEventListener('change', function () {
            const file = this.files[0];
            
            if (file) {
                // Validate file type client-side
                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPEG, PNG, and WebP images are allowed.');
                    this.value = '';
                    return;
                }

                // Validate file size (5 MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File exceeds 5 MB limit.');
                    this.value = '';
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function (e) {
                    // Hide initial placeholder
                    if (placeholderInitial) {
                        placeholderInitial.style.display = 'none';
                    }

                    // Remove existing preview image if any
                    const existingImg = previewContainer.querySelector('#preview-img');
                    if (existingImg) {
                        existingImg.remove();
                    }

                    // Remove any "Missing file" placeholder
                    const missingPlaceholder = previewContainer.querySelector('.poster-placeholder-small');
                    if (missingPlaceholder) {
                        missingPlaceholder.remove();
                    }

                    // Create new preview image
                    const img = document.createElement('img');
                    img.id = 'preview-img';
                    img.src = e.target.result;
                    img.alt = 'Poster preview';
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    
                    // Insert at the beginning of preview container
                    previewContainer.insertBefore(img, previewContainer.firstChild);
                };
                reader.readAsDataURL(file);
            } else {
                // No file selected - restore initial state for add, or keep current for edit
                if (placeholderInitial) {
                    placeholderInitial.style.display = 'flex';
                }
                const existingImg = previewContainer.querySelector('#preview-img');
                if (existingImg) {
                    existingImg.remove();
                }
            }
        });
    }
});

// Password visibility toggle for auth forms
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const wrapper = this.closest('.input-wrapper');
            const input = wrapper?.querySelector('input[type="password"], input[type="text"]');
            const icon = this.querySelector('i');

            if (input && icon) {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
                this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            }
        });
    });
});

// Character counter for review textarea
document.addEventListener('DOMContentLoaded', function () {
    const reviewTextarea = document.getElementById('review_text');
    const charCount = document.getElementById('char-count');

    if (reviewTextarea && charCount) {
        reviewTextarea.addEventListener('input', function () {
            charCount.textContent = this.value.length;
        });
    }
});

// Delete review confirmation
document.addEventListener('DOMContentLoaded', function () {
    const deleteBtn = document.getElementById('delete-review-btn');
    const cancelBtn = document.getElementById('cancel-edit');
    const deleteForm = document.getElementById('delete-review-form');
    const reviewForm = document.getElementById('review-form');

    if (deleteBtn && deleteForm && reviewForm) {
        deleteBtn.addEventListener('click', function () {
            if (confirm('Are you sure you want to delete your review? This action cannot be undone.')) {
                deleteForm.submit();
            }
        });
    }

    if (cancelBtn && reviewForm) {
        cancelBtn.addEventListener('click', function () {
            reviewForm.reset();
            // If there was existing content, reload the page to restore it
            if (reviewForm.querySelector('textarea').defaultValue) {
                location.reload();
            }
        });
    }
});

// Live search suggestions (dropdown with keyboard navigation)
document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('.navbar-search input[name="search"], .mobile-search input[name="search"]');
    inputs.forEach(function (input) {
        const form = input.closest('form');
        if (!form) return;

        const wrapper = form.classList.contains('navbar-search') ? form.parentElement : form;
        let dropdown = wrapper.querySelector('.search-suggestions');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.className = 'search-suggestions';
            dropdown.setAttribute('role', 'listbox');
            dropdown.setAttribute('aria-label', 'Search suggestions');
            wrapper.appendChild(dropdown);
        }

        let items = [];
        let activeIndex = -1;
        let debounceTimer = null;
        let currentQuery = '';

        function hide() {
            dropdown.classList.remove('open');
            dropdown.innerHTML = '';
            items = [];
            activeIndex = -1;
        }

        function render(results, query) {
            dropdown.innerHTML = '';
            items = [];
            activeIndex = -1;
            if (!results.length) {
                const empty = document.createElement('div');
                empty.className = 'search-suggestion-empty';
                empty.textContent = 'No movies found';
                dropdown.appendChild(empty);
                dropdown.classList.add('open');
                return;
            }
            results.forEach(function (r, i) {
                const a = document.createElement('a');
                a.className = 'search-suggestion';
                a.href = r.url;
                a.setAttribute('role', 'option');
                a.setAttribute('data-index', i);

                const img = document.createElement('img');
                img.src = r.poster || '';
                img.alt = '';
                img.loading = 'lazy';
                img.onerror = function () { this.style.visibility = 'hidden'; };

                const text = document.createElement('span');
                text.className = 'search-suggestion-text';
                const title = document.createElement('span');
                title.className = 'search-suggestion-title';
                title.textContent = r.title;
                const meta = document.createElement('span');
                meta.className = 'search-suggestion-meta';
                meta.textContent = r.year || '';
                text.appendChild(title);
                if (r.year) text.appendChild(meta);

                a.appendChild(img);
                a.appendChild(text);
                dropdown.appendChild(a);
                items.push(a);
            });
            dropdown.classList.add('open');
        }

        function fetchSuggestions(q) {
            if (q === currentQuery) return;
            currentQuery = q;
            if (q.length < 2) { hide(); return; }
            fetch('search_suggest.php?q=' + encodeURIComponent(q))
                .then(function (res) { return res.json(); })
                .then(function (data) { render(data.results || [], q); })
                .catch(function () { hide(); });
        }

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const q = this.value.trim();
            debounceTimer = setTimeout(function () { fetchSuggestions(q); }, 200);
        });

        input.addEventListener('keydown', function (e) {
            if (!dropdown.classList.contains('open')) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
                updateActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                updateActive();
            } else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                e.preventDefault();
                window.location.href = items[activeIndex].href;
            } else if (e.key === 'Escape') {
                hide();
            }
        });

        function updateActive() {
            items.forEach(function (el, i) {
                el.classList.toggle('active', i === activeIndex);
            });
            if (activeIndex >= 0 && items[activeIndex]) {
                items[activeIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) hide();
        });
    });
});

// Admin sidebar toggle (mobile)
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('admin-sidebar-toggle');
    const sidebar = document.getElementById('admin-sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            const open = sidebar.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!sidebar.classList.contains('open')) return;
            if (sidebar.contains(e.target) || toggle.contains(e.target)) return;
            sidebar.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    }
});

// Mobile Navigation Toggle & Flash Notification Dismissal
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');

    if (menuToggle && mobileMenu) {
        const setMenuState = function (isOpen) {
            menuToggle.classList.toggle('active', isOpen);
            mobileMenu.classList.toggle('active', isOpen);
            menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        };

        menuToggle.addEventListener('click', function () {
            setMenuState(!mobileMenu.classList.contains('active'));
        });

        // Close dropdown when clicking a nav link
        mobileMenu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setMenuState(false);
            });
        });

        // Close the menu when clicking outside of it
        document.addEventListener('click', function (event) {
            if (!mobileMenu.classList.contains('active')) return;
            if (mobileMenu.contains(event.target) || menuToggle.contains(event.target)) return;
            setMenuState(false);
        });

        // Close the menu with the Escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && mobileMenu.classList.contains('active')) {
                setMenuState(false);
                menuToggle.focus();
            }
        });

        // Close the menu when the viewport grows past the tablet breakpoint
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024 && mobileMenu.classList.contains('active')) {
                setMenuState(false);
            }
        });
    }

    // Flash notification: manual dismiss for every type
    const flashMessage = document.getElementById('flash-message');
    if (flashMessage) {
        const closeButton = flashMessage.querySelector('.alert-close');
        if (closeButton) {
            closeButton.addEventListener('click', function () {
                flashMessage.remove();
            });
        }

        // Success and info messages fade out on their own. Error messages
        // stay on screen so the user can actually read them.
        if (!flashMessage.classList.contains('alert-error')) {
            setTimeout(function () {
                flashMessage.style.opacity = '0';
                setTimeout(function () {
                    flashMessage.remove();
                }, 300);
            }, 5000);
        }
    }
});
