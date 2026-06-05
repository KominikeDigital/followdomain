// Client-side helper for generating subdirectory-aware URLs
const getUrl = (path) => {
    const base = window.BASE_PATH || '';
    return base + '/' + path.replace(/^\//, '');
};

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Mobile Menu Popover Management
    const mobileNav = document.getElementById('mobile-nav');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (menuToggle && mobileNav) {
        menuToggle.addEventListener('click', () => {
            const expanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', !expanded);
        });
        
        mobileNav.addEventListener('toggle', (event) => {
            if (event.newState === 'closed') {
            menuToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Header register CTA text rotator
    const registerRotator = document.querySelector('.js-register-rotator');
    if (registerRotator) {
        const originalText = registerRotator.textContent.trim() || 'Register';
        const altText = registerRotator.dataset.altText || 'Free';
        const labels = [altText, originalText];
        let labelIndex = 0;

        registerRotator.textContent = labels[labelIndex];
        setInterval(() => {
            labelIndex = (labelIndex + 1) % labels.length;
            registerRotator.classList.add('is-changing');
            setTimeout(() => {
                registerRotator.textContent = labels[labelIndex];
                registerRotator.classList.remove('is-changing');
            }, 160);
        }, 3200);
    }

    // Flight information display animation for step titles
    const flightTitles = document.querySelectorAll('.flight-display-title');
    if (flightTitles.length) {
        const glyphs = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        const animateFlightTitle = (node) => {
            const finalText = node.dataset.flightText || node.textContent.trim();
            let frame = 0;
            const totalFrames = Math.max(16, finalText.length + 8);
            clearInterval(node._flightTimer);
            node._flightTimer = setInterval(() => {
                const settled = Math.floor((frame / totalFrames) * finalText.length);
                node.textContent = finalText
                    .split('')
                    .map((char, index) => {
                        if (char === ' ') return ' ';
                        if (index < settled) return char;
                        return glyphs[Math.floor(Math.random() * glyphs.length)];
                    })
                    .join('');
                frame++;
                if (frame > totalFrames) {
                    clearInterval(node._flightTimer);
                    node.textContent = finalText;
                }
            }, 42);
        };

        const startFlightAnimations = () => {
            flightTitles.forEach((node, index) => {
                setTimeout(() => animateFlightTitle(node), index * 180);
            });
        };

        const titleObserver = 'IntersectionObserver' in window
            ? new IntersectionObserver((entries) => {
                if (entries.some(entry => entry.isIntersecting)) {
                    startFlightAnimations();
                    titleObserver.disconnect();
                }
            }, { threshold: 0.3 })
            : null;

        if (titleObserver) {
            flightTitles.forEach(node => titleObserver.observe(node));
        } else {
            startFlightAnimations();
        }
    }

    // Price comparison tabs
    document.querySelectorAll('[data-comparison-tabs]').forEach((widget) => {
        const tabs = widget.querySelectorAll('[data-tab-target]');
        const panels = widget.querySelectorAll('[data-tab-panel]');
        tabs.forEach((tabButton) => {
            tabButton.addEventListener('click', () => {
                const target = tabButton.getAttribute('data-tab-target');
                tabs.forEach((btn) => btn.classList.toggle('active', btn === tabButton));
                panels.forEach((panel) => {
                    panel.classList.toggle('active', panel.getAttribute('data-tab-panel') === target);
                });
            });
        });
    });

    // Registration password strength meter
    const regPass = document.getElementById('regPass');
    const strengthBox = document.getElementById('passwordStrength');
    if (regPass && strengthBox) {
        const strengthText = document.getElementById('passwordStrengthText');
        const strengthBar = document.getElementById('passwordStrengthBar');
        const ruleNodes = Array.from(strengthBox.querySelectorAll('[data-rule]'));

        const updatePasswordStrength = () => {
            const value = regPass.value || '';
            const checks = {
                length: value.length >= 8,
                case: /[a-z]/.test(value) && /[A-Z]/.test(value),
                number: /\d/.test(value),
                symbol: /[^a-zA-Z0-9]/.test(value)
            };
            let score = Object.values(checks).filter(Boolean).length;
            if (value.length >= 12 && score >= 3) score += 1;

            const level = score >= 4 ? 'strong' : score === 3 ? 'good' : score === 2 ? 'fair' : 'weak';
            strengthBox.setAttribute('data-score', level);
            if (strengthText) {
                strengthText.textContent = strengthBox.dataset[level] || level;
            }
            if (strengthBar) {
                strengthBar.style.width = `${Math.max(1, Math.min(score, 4)) * 25}%`;
            }
            ruleNodes.forEach((node) => {
                const rule = node.getAttribute('data-rule');
                node.classList.toggle('met', !!checks[rule]);
            });

            if (value.length > 0 && score < 3) {
                regPass.setCustomValidity(strengthBox.dataset.invalid || 'Please choose a stronger password.');
            } else {
                regPass.setCustomValidity('');
            }
        };

        regPass.addEventListener('input', updatePasswordStrength);
        const registerForm = regPass.closest('form');
        if (registerForm) {
            registerForm.addEventListener('submit', () => {
                updatePasswordStrength();
            });
        }
        updatePasswordStrength();
    }

    // 2. Expiration Countdown Timer (Real-time update)
    const timerElement = document.getElementById('countdownTimer');
    if (timerElement) {
        const expirationString = timerElement.getAttribute('data-time');
        
        if (expirationString) {
            const expDate = new Date(expirationString.replace(' ', 'T')).getTime();
            
            const daysNode = document.getElementById('cd-days');
            const hoursNode = document.getElementById('cd-hours');
            const minutesNode = document.getElementById('cd-minutes');
            const secondsNode = document.getElementById('cd-seconds');
            
            const updateTimer = () => {
                const now = new Date().getTime();
                const distance = expDate - now;
                
                if (distance < 0) {
                    clearInterval(timerInterval);
                    if (daysNode) daysNode.textContent = '00';
                    if (hoursNode) hoursNode.textContent = '00';
                    if (minutesNode) minutesNode.textContent = '00';
                    if (secondsNode) secondsNode.textContent = '00';
                    
                    const parentCard = timerElement.closest('.countdown-card');
                    if (parentCard) {
                        parentCard.classList.add('expired');
                        const subtitle = parentCard.querySelector('.card-subtitle');
                        if (subtitle) subtitle.textContent = timerElement.dataset.expiredText || 'Expired';
                    }
                    return;
                }
                
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                if (daysNode) daysNode.textContent = String(days).padStart(2, '0');
                if (hoursNode) hoursNode.textContent = String(hours).padStart(2, '0');
                if (minutesNode) minutesNode.textContent = String(minutes).padStart(2, '0');
                if (secondsNode) secondsNode.textContent = String(seconds).padStart(2, '0');
            };
            
            updateTimer();
            const timerInterval = setInterval(updateTimer, 1000);
        }
    }

    // 3. Asynchronous Follow Form Submission (Public Page)
    const followForm = document.getElementById('followForm');
    const ajaxAlert = document.getElementById('ajaxFollowAlert');
    
    if (followForm && ajaxAlert) {
        followForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const formData = new FormData(followForm);
            const submitBtn = followForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = followForm.dataset.loadingText || 'Adding...';
            
            ajaxAlert.style.display = 'none';
            ajaxAlert.className = 'alert';
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
                
                ajaxAlert.textContent = data.message;
                ajaxAlert.style.display = 'block';
                
                if (data.success) {
                    ajaxAlert.classList.add('alert-success');
                    followForm.reset();
                    
                    const followerCountNumNode = document.querySelector('.follower-count-num');
                    if (followerCountNumNode) {
                        const currentCount = parseInt(followerCountNumNode.textContent) || 0;
                        followerCountNumNode.textContent = currentCount + 1;
                    }
                } else {
                    ajaxAlert.classList.add('alert-error');
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
                ajaxAlert.textContent = followForm.dataset.errorText || 'An error occurred during the request.';
                ajaxAlert.style.display = 'block';
                ajaxAlert.classList.add('alert-error');
            });
        });
    }

    // 4. Domain search input validator (Public page)
    const domainInput = document.getElementById('domainInput');
    const searchForm = document.getElementById('searchForm');
    
    if (domainInput && searchForm) {
        searchForm.addEventListener('submit', (e) => {
            const rawVal = domainInput.value.trim();
            const domainPattern = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9](?:\.[a-zA-Z]{2,})+$/;
            
            let cleanVal = rawVal.toLowerCase()
                .replace(/^https?:\/\//, '')
                .replace(/^www\./, '')
                .split('/')[0]
                .split(':')[0];
                
            if (!domainPattern.test(cleanVal)) {
                e.preventDefault();
                alert('Lütfen geçerli bir alan adı formatı girin (örn: google.com veya btk.gov.tr).');
                domainInput.focus();
            }
        });
    }

    // 5. Header User Menu Dropdown toggler
    const userBubbleBtn = document.getElementById('userBubbleBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    const langDropdownBtn = document.getElementById('langDropdownBtn');
    const langDropdownMenu = document.getElementById('langDropdownMenu');
    
    if (userBubbleBtn && userDropdownMenu) {
        userBubbleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('show');
            if (langDropdownMenu) langDropdownMenu.classList.remove('show');
        });
    }

    if (langDropdownBtn && langDropdownMenu) {
        langDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            langDropdownMenu.classList.toggle('show');
            if (userDropdownMenu) userDropdownMenu.classList.remove('show');
        });
    }
    
    // Theme Dropdown & localStorage Sync
    const themeDropdownBtn = document.getElementById('themeDropdownBtn');
    const themeDropdownMenu = document.getElementById('themeDropdownMenu');
    const themeItems = document.querySelectorAll('.theme-item');
    const themeCurrentText = themeDropdownBtn ? themeDropdownBtn.querySelector('.theme-current-text') : null;

    function applyTheme(theme) {
        let activeTheme = theme;
        if (theme === 'system') {
            activeTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', activeTheme);
        document.documentElement.setAttribute('data-selected-theme', theme);

        // Update active class in dropdown
        themeItems.forEach(item => {
            if (item.getAttribute('data-theme-val') === theme) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Update button text
        const activeItem = document.querySelector(`.theme-item[data-theme-val="${theme}"]`);
        if (activeItem && themeCurrentText) {
            themeCurrentText.textContent = activeItem.querySelector('.theme-text').textContent.trim();
        }
    }

    // Set initial active state based on stored theme
    const savedTheme = localStorage.getItem('theme') || 'system';
    applyTheme(savedTheme);

    // Watch for system preference changes if 'system' is active
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (localStorage.getItem('theme') === 'system' || !localStorage.getItem('theme')) {
            applyTheme('system');
        }
    });

    if (themeDropdownBtn && themeDropdownMenu) {
        themeDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            themeDropdownMenu.classList.toggle('show');
            if (userDropdownMenu) userDropdownMenu.classList.remove('show');
            if (langDropdownMenu) langDropdownMenu.classList.remove('show');
        });
    }

    themeItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            const selectedVal = item.getAttribute('data-theme-val');
            localStorage.setItem('theme', selectedVal);
            applyTheme(selectedVal);
            if (themeDropdownMenu) themeDropdownMenu.classList.remove('show');
        });
    });

    if (userBubbleBtn && userDropdownMenu) {
        userBubbleBtn.addEventListener('click', (e) => {
            if (themeDropdownMenu) themeDropdownMenu.classList.remove('show');
        });
    }

    if (langDropdownBtn && langDropdownMenu) {
        langDropdownBtn.addEventListener('click', (e) => {
            if (themeDropdownMenu) themeDropdownMenu.classList.remove('show');
        });
    }

    document.addEventListener('click', () => {
        if (userDropdownMenu) userDropdownMenu.classList.remove('show');
        if (langDropdownMenu) langDropdownMenu.classList.remove('show');
        if (themeDropdownMenu) themeDropdownMenu.classList.remove('show');
    });

    // 6. Home Blog Carousel Slider Navigation with Autoplay & Pause on Hover
    const blogSliderContainer = document.getElementById('blogSliderContainer');
    const blogSliderPrev = document.getElementById('blogSliderPrev');
    const blogSliderNext = document.getElementById('blogSliderNext');

    if (blogSliderContainer && blogSliderPrev && blogSliderNext) {
        const scrollAmount = 350 + 24; // Card width + gap
        
        blogSliderPrev.addEventListener('click', () => {
            blogSliderContainer.scrollBy({
                left: -scrollAmount,
                behavior: 'smooth'
            });
        });

        blogSliderNext.addEventListener('click', () => {
            blogSliderContainer.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
        });

        const toggleButtons = () => {
            const scrollLeft = blogSliderContainer.scrollLeft;
            const maxScroll = blogSliderContainer.scrollWidth - blogSliderContainer.clientWidth;
            
            if (scrollLeft <= 5) {
                blogSliderPrev.style.opacity = '0.4';
                blogSliderPrev.style.pointerEvents = 'none';
            } else {
                blogSliderPrev.style.opacity = '1';
                blogSliderPrev.style.pointerEvents = 'auto';
            }

            if (scrollLeft >= maxScroll - 5) {
                blogSliderNext.style.opacity = '0.4';
                blogSliderNext.style.pointerEvents = 'none';
            } else {
                blogSliderNext.style.opacity = '1';
                blogSliderNext.style.pointerEvents = 'auto';
            }
        };

        blogSliderContainer.addEventListener('scroll', toggleButtons);
        setTimeout(toggleButtons, 100);
        window.addEventListener('resize', toggleButtons);

        // Autoplay logic
        let autoplayInterval = null;
        let isHovered = false;
        let autoplayDirection = 1; // 1 = forward, -1 = backward

        const startAutoplay = () => {
            if (autoplayInterval) clearInterval(autoplayInterval);
            autoplayInterval = setInterval(() => {
                if (!isHovered) {
                    blogSliderContainer.scrollLeft += 0.8 * autoplayDirection;
                    
                    const maxScroll = blogSliderContainer.scrollWidth - blogSliderContainer.clientWidth;
                    if (blogSliderContainer.scrollLeft >= maxScroll - 2) {
                        // Loop back to start
                        blogSliderContainer.scrollLeft = 0;
                    } else if (blogSliderContainer.scrollLeft <= 0) {
                        autoplayDirection = 1;
                    }
                }
            }, 20); // 50fps smooth scrolling. 0.8px * 50 = 40px/sec, very gentle flow.
        };

        const stopAutoplay = () => {
            if (autoplayInterval) {
                clearInterval(autoplayInterval);
                autoplayInterval = null;
            }
        };

        // Hover listeners on the outer slider container (to cover buttons as well)
        const blogSliderOuter = document.querySelector('.blog-slider-outer');
        if (blogSliderOuter) {
            blogSliderOuter.addEventListener('mouseenter', () => {
                isHovered = true;
            });
            blogSliderOuter.addEventListener('mouseleave', () => {
                isHovered = false;
            });
        }

        // Start autoplay
        startAutoplay();
    }
});

/* --------------------------------------------------
   Add Domain Modal Controllers (Global Functions)
   -------------------------------------------------- */
function openAddDomainModal() {
    const dialog = document.getElementById('addDomainDialog');
    if (dialog) {
        dialog.showModal();
    }
}

function closeAddDomainModal() {
    const dialog = document.getElementById('addDomainDialog');
    if (dialog) {
        dialog.close();
    }
}

function switchModalTab(tab) {
    const btnSingle = document.getElementById('tabBtnSingle');
    const btnBulk = document.getElementById('tabBtnBulk');
    const blockSingle = document.getElementById('modalSingleBlock');
    const blockBulk = document.getElementById('modalBulkBlock');
    const modeInput = document.getElementById('modalInputMode');
    const submitBtn = document.getElementById('modalSubmitBtn');
    
    if (tab === 'single') {
        btnSingle.classList.add('active');
        btnBulk.classList.remove('active');
        blockSingle.style.display = 'block';
        blockBulk.style.display = 'none';
        modeInput.value = 'single';
        if (submitBtn) {
            submitBtn.textContent = submitBtn.getAttribute('data-text-single') || '+ Save';
        }
    } else {
        btnSingle.classList.remove('active');
        btnBulk.classList.add('active');
        blockSingle.style.display = 'none';
        blockBulk.style.display = 'block';
        modeInput.value = 'bulk';
        if (submitBtn) {
            submitBtn.textContent = submitBtn.getAttribute('data-text-bulk') || '+ Import';
        }
    }
}

function toggleAlertTag(button) {
    button.classList.toggle('active');
    const isActive = button.classList.contains('active');
    const val = button.getAttribute('data-val');
    
    // Find the parent form group or container to locate the correct target input
    const container = button.closest('.form-group') || button.closest('form') || button.parentElement;
    let input = null;
    if (container) {
        input = container.querySelector('[id$="alert_val_' + val + '"]') || 
                container.querySelector('[name="alerts[' + val + ']"]');
    }
    
    // Fallback to document global if not found in parent context
    if (!input) {
        input = document.getElementById('alert_val_' + val) || document.getElementById('config_alert_val_' + val);
    }
    
    if (input) {
        input.value = isActive ? '1' : '0';
    }
}

/* --------------------------------------------------
   Domains List Controllers
   -------------------------------------------------- */
function toggleFavorite(domainName, button) {
    const formData = new FormData();
    formData.append('action', 'toggle_favorite');
    formData.append('domain_name', domainName);
    
    fetch(getUrl('panel/domains'), {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.is_favorite == 1) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
        }
    })
    .catch(err => console.error('Error toggling favorite:', err));
}

function openAlertConfig(domainName, n60, n30, n14, n7, n3, n1) {
    const dialog = document.getElementById('alertConfigDialog');
    const titleNode = document.getElementById('configDialogDomainName');
    const inputNode = document.getElementById('configAlertDomainInput');
    
    if (dialog && titleNode && inputNode) {
        titleNode.textContent = domainName;
        inputNode.value = domainName;
        
        const settings = {
            '60': n60,
            '30': n30,
            '14': n14,
            '7': n7,
            '3': n3,
            '1': n1
        };
        
        // Reset tag buttons inside configuration
        const tagsContainer = document.getElementById('configDialogTags');
        const tagButtons = tagsContainer.querySelectorAll('.alert-tag-btn');
        
        tagButtons.forEach(btn => {
            const val = btn.getAttribute('data-val');
            const isActive = settings[val] === 1;
            
            if (isActive) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
            
            const hiddenInput = document.getElementById('config_alert_val_' + val);
            if (hiddenInput) {
                hiddenInput.value = isActive ? '1' : '0';
            }
        });
        
        dialog.showModal();
    }
}

function closeAlertConfig() {
    const dialog = document.getElementById('alertConfigDialog');
    if (dialog) {
        dialog.close();
    }
}

function deleteDomain(domainName, confirmMsg) {
    const msg = confirmMsg || `Are you sure you want to stop tracking "${domainName}"?`;
    if (confirm(msg)) {
        window.location.href = getUrl(`panel/domains?delete_domain=${encodeURIComponent(domainName)}`);
    }
}

/* Layout Controls */
function togglePreviews() {
    const container = document.getElementById('domainsCardsContainer');
    const btn = document.getElementById('btnTogglePreviews');
    if (container && btn) {
        container.classList.toggle('previews-hidden');
        const textOn = btn.getAttribute('data-text-on') || '👁 Previews On';
        const textOff = btn.getAttribute('data-text-off') || '👁 Previews Off';
        if (container.classList.contains('previews-hidden')) {
            btn.textContent = textOff;
        } else {
            btn.textContent = textOn;
        }
    }
}

function changeLayout(layout) {
    const container = document.getElementById('domainsCardsContainer');
    const btnGrid = document.getElementById('btnLayoutGrid');
    const btnList = document.getElementById('btnLayoutList');
    
    if (container && btnGrid && btnList) {
        if (layout === 'grid') {
            container.classList.remove('list-view');
            btnGrid.classList.add('active');
            btnList.classList.remove('active');
        } else {
            container.classList.add('list-view');
            btnGrid.classList.remove('active');
            btnList.classList.add('active');
        }
    }
}

function handleSearch(event) {
    const query = event.target.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.domain-info-card');
    
    cards.forEach(card => {
        const domain = card.getAttribute('data-domain').toLowerCase();
        if (domain.includes(query)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function handleSortChange(select) {
    const sortBy = select.value;
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortBy);
    window.location.href = url.toString();
}
