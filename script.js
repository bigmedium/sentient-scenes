document.addEventListener('DOMContentLoaded', () => {
   // Debug mode flag
    const DEBUG = false;

    // Core element references
    const sceneInput = document.getElementById('sceneInput');
    const submitButton = document.getElementById('submitScene');
    const clearButton = document.getElementById('clearInput');
    const characterElement = document.getElementById('characterSquare');
    const captionText = document.getElementById('captionText');
    const root = document.documentElement;
    let isRequestInProgress = false;

    // Debug logging helpers
    function debugLog(label, data, type = 'default') {
        if (!DEBUG) return;
        
        // Different styles for different types of logs
        const styles = {
            default: 'color: #99ccff; font-weight: bold;',
            request: 'color: #ff9966; font-weight: bold;',
            response: 'color: #99ff99; font-weight: bold;',
            error: 'color: #ff6666; font-weight: bold;'
        };
        
        console.groupCollapsed(`%c${label}`, styles[type]);
        
        if (type === 'response' && data) {
            // Log scene content
            console.group('Scene Data:');
            console.log('Caption:', data.caption);
            console.log('Colors:', {
                background: data.background,
                content: data.content
            });
            console.log('Font:', data['font-family']);
            console.log('Shadow:', data.shadow);
            console.groupEnd();

            // Log animation details
            console.group('Animation:');
            console.log('Animation:', data.animation);
            console.log('Keyframes:', data.keyframes);
            console.log('Fallback:', data.fallback);
            console.groupEnd();

            // Log token usage in table format
            if (data.usage) {
                console.group('Token Usage:');
                console.table({
                    'Input Tokens': { value: data.usage.input_tokens },
                    'Output Tokens': { value: data.usage.output_tokens },
                    'Estimated Cost': { value: `$${data.usage.est_usd.toFixed(6)}` }
                });
                console.groupEnd();
            }
        } else {
            console.log(data);
        }
        
        console.groupEnd();
    }

    /**
     * Creates a human-readable description of animation patterns
     * @param {Object} sceneData - The scene configuration data
     * @returns {string} A description of the animation
     */
    function createMovementDescription(sceneData) {
        const patterns = [];
        const keyframes = sceneData.keyframes.toLowerCase();

        // Check for different movement patterns
        if (keyframes.includes('translatex') && keyframes.includes('translatey')) {
            patterns.push('moves diagonally');
        } else if (keyframes.includes('translatex')) {
            patterns.push('moves horizontally');
        } else if (keyframes.includes('translatey')) {
            patterns.push('moves vertically');
        }

        if (keyframes.includes('rotate')) {
            patterns.push('spins');
        }
        if (keyframes.includes('scale')) {
            patterns.push('changes size');
        }

        // Get animation duration
        const durationMatch = sceneData.animation.match(/(\d+(?:\.\d+)?)s/);
        const duration = durationMatch ? durationMatch[1] : null;

        // Construct the description
        let description = 'Character ';
        if (patterns.length > 0) {
            description += patterns.join(' and ');
            if (duration) {
                description += ` over ${duration} seconds`;
            }
        } else {
            description = 'Character remains centered with subtle animation';
        }

        return description;
    }

    /**
     * Updates the ARIA description of the current animation
     * @param {Object} sceneData - The scene configuration data
     */
    function updateMovementDescription(sceneData) {
        const movementDesc = document.getElementById('movementDescription');
        const description = createMovementDescription(sceneData);
        movementDesc.textContent = description;
    }

    /**
     * Shows an error message to the user
     * @param {string} message - The error message to display
     * @param {string} type - The type of error (system_error or input_error)
     */
    function showError(message, type) {
        const errorEmoji = type === 'system_error' ? '⚠️' : '💭';
        captionText.textContent = `${errorEmoji} ${message}`;
        
        const movementDesc = document.getElementById('movementDescription');
        movementDesc.textContent = 'Scene generation failed';
        
        // Apply subtle visual feedback
        characterElement.classList.add('error');
        setTimeout(() => {
            characterElement.classList.remove('error');
        }, 1000);
    }

    /**
     * Removes any existing animation from the character element
     * and cleans up custom animation styles
     */
    function cleanupPreviousAnimation(element) {
        element.style.removeProperty('animation');
        element.className = 'scene-character';
        
        // Remove any custom animation styles
        const customStyles = document.querySelectorAll('style[data-scene-animation]');
        customStyles.forEach(style => style.remove());
    }

    /**
     * Applies a fallback animation class when custom animation fails
     */
    function applyFallback(fallbackName, element) {
        const validFallbacks = ['bounce', 'float', 'jitter', 'pulse', 'drift'];
        const fallbackClass = validFallbacks.includes(fallbackName) ? fallbackName : 'float';
        element.classList.add(fallbackClass);

        // Update movement description for fallback
        const movementDesc = document.getElementById('movementDescription');
        movementDesc.textContent = `Character performs ${fallbackClass} animation`;
    }

    /**
     * Updates the scene with synchronized transitions
     */
    function updateScene(sceneData) {
        // Schedule all visual updates in the next frame
        requestAnimationFrame(() => {
            // First, remove existing animations to avoid conflicts
            cleanupPreviousAnimation(characterElement);

            // Batch all visual updates together
            const updates = () => {
                try {
                    // Update CSS variables
                    root.style.setProperty('--background-color', sceneData.background);
                    root.style.setProperty('--foreground-color', sceneData.content);
                    root.style.setProperty('--glow-effect', sceneData.shadow);
                    root.style.setProperty('--caption-font', sceneData['font-family']);

                    // Update caption text
                    captionText.textContent = sceneData.caption;

                    // Create and append new animation style
                    const styleElement = document.createElement('style');
                    styleElement.setAttribute('data-scene-animation', 'current');
                    styleElement.textContent = sceneData.keyframes;
                    document.head.appendChild(styleElement);

                    // Check for reduced motion preference
                    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    if (prefersReducedMotion) {
                        const movementDesc = document.getElementById('movementDescription');
                        movementDesc.textContent = 'Animation disabled due to reduced motion preference';
                        return;
                    }

                    // Apply the animation
                    if (!styleElement.sheet || !styleElement.sheet.cssRules.length) {
                        throw new Error('Invalid keyframes');
                    }

                    characterElement.style.animation = sceneData.animation;
                    const computedStyle = window.getComputedStyle(characterElement);
                    
                    if (computedStyle.animation === 'none' || computedStyle.animation === '') {
                        throw new Error('Invalid animation');
                    }

                    updateMovementDescription(sceneData);
                } catch (error) {
                    console.error('Animation application failed:', error);
                    styleElement?.remove();
                    applyFallback(sceneData.fallback || 'float', characterElement);
                }
            };

            // Execute all updates in the next frame to ensure synchronization
            requestAnimationFrame(updates);
        });
    }

    /**
     * Updates UI state during scene generation
     * @param {boolean} isLoading - Whether the scene is being generated
     */
    function updateUIState(isLoading) {
        const buttonText = submitButton.querySelector('.button-text');
        const loadingDots = submitButton.querySelector('.loading-dots');
        
        if (isLoading) {
            buttonText.classList.add('hidden');
            loadingDots.classList.remove('hidden');
            loadingDots.classList.add('animate');
            submitButton.disabled = true;
            
            // Disable nudge chips
            document.querySelectorAll('.nudge-chip').forEach(chip => {
                chip.classList.add('disabled');
                chip.setAttribute('aria-disabled', 'true');
            });
        } else {
            buttonText.classList.remove('hidden');
            loadingDots.classList.add('hidden');
            loadingDots.classList.remove('animate');
            submitButton.disabled = false;
            
            // Re-enable nudge chips
            document.querySelectorAll('.nudge-chip').forEach(chip => {
                chip.classList.remove('disabled');
                chip.setAttribute('aria-disabled', 'false');
            });
        }
    }

    /**
     * Handles form submission and scene generation
     */
    async function handleSubmit() {
        if (isRequestInProgress) return;

        const description = sceneInput.value.trim();
        if (!description) return;

        isRequestInProgress = true;
        updateUIState(true);
    
        try {
            debugLog('🔼 Scene Request', { description }, 'request');

            const response = await fetch('generate-scene.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ description })
            });
            
            const data = await response.json();
            
            debugLog('🔽 Scene Response', data, 'response');
            
            if (!response.ok) {
                if (data.error) {
                    if (data.type === 'system_error') {
                        showError('Something went wrong. Please try again.', 'system_error');
                    } else {
                        showError(data.message || 'Failed to generate scene', 'input_error');
                    }
                    return;
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }
    
            updateScene(data);
            
        } catch (error) {
            debugLog('⛔️ Error', error, 'error');
            showError('Unable to connect to the server. Please try again.', 'system_error');
        } finally {
            isRequestInProgress = false;
            updateUIState(false);
        }
    }

    /**
     * Set up keyboard navigation for nudge chips
     */
    function setupNudgeNavigation() {
        const nudgeChips = document.querySelectorAll('.nudge-chip');
        
        nudgeChips.forEach((chip, index) => {
            chip.addEventListener('keydown', (e) => {
                let targetChip = null;
                
                switch (e.key) {
                    case 'ArrowRight':
                    case 'ArrowDown':
                        e.preventDefault();
                        targetChip = nudgeChips[index + 1] || nudgeChips[0];
                        break;
                    case 'ArrowLeft':
                    case 'ArrowUp':
                        e.preventDefault();
                        targetChip = nudgeChips[index - 1] || nudgeChips[nudgeChips.length - 1];
                        break;
                    case 'Home':
                        e.preventDefault();
                        targetChip = nudgeChips[0];
                        break;
                    case 'End':
                        e.preventDefault();
                        targetChip = nudgeChips[nudgeChips.length - 1];
                        break;
                }
                
                if (targetChip) {
                    targetChip.focus();
                }
            });

            chip.addEventListener('click', () => {
                const scene = chip.dataset.scene;
                sceneInput.value = scene;
                handleSubmit();
            });

            chip.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    chip.click();
                }
            });
        });
    }

    /**
     * Set up event listeners
     */
    function setupEventListeners() {
        submitButton.addEventListener('click', handleSubmit);
        
        sceneInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSubmit();
            }
        });

        clearButton.addEventListener('click', () => {
            sceneInput.value = '';
            sceneInput.focus();
        });

        setupNudgeNavigation();

        // Listen for reduced motion preference changes
        const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        motionQuery.addEventListener('change', () => {
            if (motionQuery.matches) {
                cleanupPreviousAnimation(characterElement);
                const movementDesc = document.getElementById('movementDescription');
                movementDesc.textContent = 'Animation disabled due to reduced motion preference';
            }
        });
    }

    // Initialize the application
    setupEventListeners();
});