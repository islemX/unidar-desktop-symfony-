// Contract and Payment Management System for UNIDAR

// Get base API URL dynamically (same logic as api.js)
const getContractApiBase = () => {
    // Try to use API_BASE_URL from api.js if available
    if (typeof API_BASE_URL !== 'undefined') {
        return API_BASE_URL;
    }

    // Otherwise, try to use getAPIBaseURL function if defined (from utils.js)
    if (typeof getAPIBaseURL === 'function') {
        try {
            return getAPIBaseURL();
        } catch (e) {
            console.warn('Error calling global getAPIBaseURL:', e);
        }
    }

    // Detect base path from current script location
    const scriptTag = document.currentScript || document.querySelector('script[src*="contracts.js"]');
    const scriptPath = scriptTag ? scriptTag.src : window.location.pathname;
    const basePath = scriptPath.substring(0, scriptPath.lastIndexOf('/js/') || scriptPath.lastIndexOf('/'));

    if (basePath && basePath !== '/') {
        return basePath + '/backend/api';
    }

    // Fallback: try to detect from window location
    const pathParts = window.location.pathname.split('/');
    const unidarIndex = pathParts.indexOf('unidar');
    if (unidarIndex !== -1) {
        return '/' + pathParts.slice(1, unidarIndex + 1).join('/') + '/backend/api';
    }

    // Final fallback
    return '/backend/api';
};

// Contract Management Functions
const ContractManager = {
    async generateContract(listingId, startDate, duration) {
        try {
            showLoadingModal('Préparation du contrat...');

            const apiBase = getContractApiBase();
            const apiUrl = `${apiBase}/contracts.php?action=generate`;
            console.log('Calling API:', apiUrl);

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    listing_id: listingId,
                    start_date: startDate,
                    duration: duration
                }),
                credentials: 'include'
            });

            // Improved response handling (similar to api.js recovery logic)
            const responseText = await response.text();
            console.log('Contract generation response text:', responseText);

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.warn('JSON parse error. Attempting to recover JSON from response.');
                const jsonStart = responseText.indexOf('{');
                const jsonEnd = responseText.lastIndexOf('}');

                if (jsonStart !== -1 && jsonEnd !== -1) {
                    const jsonString = responseText.substring(jsonStart, jsonEnd + 1);
                    try {
                        result = JSON.parse(jsonString);
                    } catch (e2) {
                        console.error('JSON recovery failed:', e2);
                    }
                }
            }

            if (!result) {
                console.error('Could not get valid JSON response from server');
                hideLoadingModal();
                showErrorModal('Erreur du serveur : Réponse invalide. Veuillez contacter l\'administrateur.');
                return null;
            }

            if (result.success) {
                hideLoadingModal();
                // Build download URL using dynamic API base URL
                const apiBase = getContractApiBase();
                const downloadUrl = result.contract_url && result.contract_url.startsWith('http')
                    ? result.contract_url
                    : `${apiBase}/contracts.php?action=download&contract_id=${result.contract_id}`;
                console.log('Contract download URL:', downloadUrl);
                ContractManager.showContractModal(listingId, downloadUrl, result.content, result.contract_id, 'student', 'generated');
                return result;
            } else {
                hideLoadingModal();
                showErrorModal(result.error || 'Erreur lors de la génération du contrat');
                return null;
            }
        } catch (error) {
            hideLoadingModal();
            console.error('Contract generation error:', error);
            showErrorModal('Erreur de connexion au serveur');
            return null;
        }
    },

    showContractSetupModal(listingId) {
        console.log('[ContractSetupModal] Opening for listing:', listingId);
        console.log('[ContractSetupModal] ContractManager available:', typeof this !== 'undefined');

        // Close any existing modals first
        this.closeAllModals();

        // Small delay to ensure cleanup is complete
        setTimeout(() => {
            const modal = document.createElement('div');
            modal.id = 'contract-setup-modal';
            modal.className = 'modal-overlay';
            modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 10000; backdrop-filter: blur(5px);';

            // Calculate default start month (next month)
            const today = new Date();
            const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);
            const defaultMonth = nextMonth.toISOString().slice(0, 7); // YYYY-MM

            modal.innerHTML = `
            <div class="modal-content" style="max-width: 500px; position: relative; z-index: 10001;">
                <div class="modal-header">
                    <h3>Paramètres du Contrat</h3>
                    <button class="modal-close" id="closeSetupModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
                </div>
                
                <div class="modal-body">
                    <p class="text-muted mb-md">Veuillez définir la durée et la date de début de votre location.</p>
                    
                    <form id="contractSetupForm">
                        <div class="form-group mb-md">
                            <label class="form-label" for="startMonth">Mois de début</label>
                            <input type="month" id="startMonth" class="form-input" value="${defaultMonth}" required>
                            <p class="text-small text-muted mt-xs">La location commencera le 1er du mois sélectionné.</p>
                        </div>
                        
                        <div class="form-group mb-xl">
                            <label class="form-label" for="duration">Durée (Mois)</label>
                            <select id="duration" class="form-input" required>
                                ${Array.from({ length: 12 }, (_, i) => i + 1).map(n =>
                `<option value="${n}" ${n === 9 ? 'selected' : ''}>${n} mois</option>`
            ).join('')}
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Générer le Contrat
                        </button>
                    </form>
                </div>
            </div>
        `;

            document.body.appendChild(modal);
            console.log('[ContractSetupModal] Modal appended to body');

            // Close on overlay click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeAllModals();
                }
            });

            // Close button - use setTimeout to ensure DOM is ready
            setTimeout(() => {
                const closeBtn = document.getElementById('closeSetupModal');
                if (closeBtn) {
                    closeBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.closeAllModals();
                    });
                    console.log('[ContractSetupModal] Close button handler attached');
                } else {
                    console.error('[ContractSetupModal] Close button not found!');
                }

                // Form submission
                const form = document.getElementById('contractSetupForm');
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const startMonth = document.getElementById('startMonth').value;
                        const duration = document.getElementById('duration').value;

                        if (!startMonth || !duration) {
                            showErrorModal('Veuillez remplir tous les champs.');
                            return;
                        }

                        // Start date is always the 1st of the selected month
                        const startDate = `${startMonth}-01`;

                        this.closeAllModals();
                        await this.generateContract(listingId, startDate, duration);
                    });
                    console.log('[ContractSetupModal] Form handler attached');
                } else {
                    console.error('[ContractSetupModal] Form not found!');
                }
            }, 100);
        }, 50);
    },

    showContractModal(listingId, contractUrl, contractContent, contractId, userRole = 'student', status = null) {
        console.log(`[ContractModal] Attempting to open. ID: ${contractId}, Status: ${status}, Role: ${userRole}`);

        try {
            // Force cleanup of any existing overlays to prevent "sticky" blurs
            // Use skipLogging to avoid console spam
            this.closeAllModals(true);

            const modal = document.createElement('div');
            modal.id = 'unidar-contract-modal';
            modal.className = 'unidar-contract-overlay';
            // Force inline styles to ensure visibility
            modal.style.cssText = 'position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(15, 23, 42, 0.75) !important; backdrop-filter: blur(12px) !important; -webkit-backdrop-filter: blur(12px) !important; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 200000 !important; padding: 20px !important; pointer-events: auto !important; visibility: visible !important; opacity: 1 !important;';

            // Check signature/payment status
            const isStudent = userRole === 'student';
            const isOwner = userRole === 'owner';
            const isSigned = ['signed_by_student', 'signed_by_owner', 'pending_payment', 'paid', 'active', 'completed'].includes(status);
            const isPaid = ['paid', 'active', 'completed'].includes(status);

            // Signature/Payment Box
            let actionBoxHTML = '';
            if (isStudent && !isSigned) {
                actionBoxHTML = `
                    <div style="margin-top: 20px; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; background: #fff;">
                        <h4 style="margin: 0 0 10px 0; color: #4f46e5;">✍️ Signature Numérique</h4>
                        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px;">Signez ci-dessous pour valider votre contrat.</p>
                        <div id="student-signature-pad" style="height: 150px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 15px;"></div>
                        <button id="btn-sign-contract" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: bold;">Valider la Signature</button>
                    </div>
                `;
            } else if (isStudent && isSigned && !isPaid) {
                actionBoxHTML = `
                    <div style="margin-top: 20px; border: 2px solid #4f46e5; padding: 20px; border-radius: 16px; background: rgba(79, 70, 229, 0.05); text-align: center;">
                        <h4 style="margin: 0 0 10px 0;">✅ Contrat Signé</h4>
                        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">Dernière étape : Réglez les frais pour activer la location.</p>
                        <button class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: bold;" onclick="PaymentManager.showPaymentModal(${listingId}, ${contractId})">
                            💳 Aller au Paiement
                        </button>
                    </div>
                `;
            } else if (isOwner) {
                actionBoxHTML = `
                    <div style="margin-top: 20px; padding: 15px; border-radius: 12px; background: #f8fafc; text-align: center; border: 1px solid #e2e8f0;">
                        <p style="font-size: 0.75rem; font-weight: bold; color: #64748b; text-transform: uppercase;">Statut du Paiement</p>
                        <div style="margin-top: 10px; font-weight: bold; color: ${isPaid ? '#10b981' : '#f59e0b'};">
                            ${isPaid ? '✅ Payé & Actif' : '⏳ Attente Paiement Étudiant'}
                        </div>
                    </div>
                `;
            }

            modal.innerHTML = `
                <div class="unidar-contract-content">
                    <div class="unidar-contract-header">
                        <h2 style="margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--color-surface-900);">📄 Visualisation du Contrat</h2>
                        <button onclick="ContractManager.closeModal()" style="border: none; background: none; font-size: 2.2rem; line-height: 1; cursor: pointer; color: #94a3b8; transition: color 0.2s;">&times;</button>
                    </div>
                    
                    <div class="unidar-contract-body">
                        <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e2e8f0; border-radius: 20px; padding: 30px; margin-bottom: 24px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                            <div style="display: flex; align-items: flex-start; gap: 20px; margin-bottom: 20px;">
                                <div style="font-size: 3rem; background: white; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">📑</div>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 5px 0; color: #1e293b; font-size: 1.25rem; font-weight: 700;">Document Officiel</h4>
                                    <p style="font-size: 0.95rem; color: #64748b; margin: 0;">Identifiant de contrat: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-weight: bold; color: #475569;">#${contractId}</code></p>
                                    <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 4px;">Généré le ${new Date().toLocaleDateString()} par UNIDAR</p>
                                </div>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <a href="${contractUrl}" target="_blank" class="btn btn-primary" style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px; font-weight: 800; border-radius: 12px; text-decoration: none; background: var(--color-brand); color: white; transition: all 0.3s ease;">
                                    <span>👁️ Voir le Contrat PDF Complet</span>
                                </a>
                                <p style="text-align: center; font-size: 0.75rem; color: #94a3b8;">Le document s'ouvrira dans un nouvel onglet pour signature physique ou impression si nécessaire.</p>
                            </div>
                        </div>
                        
                        <div style="border-top: 1px dashed #e2e8f0; margin: 30px 0;"></div>

                        ${actionBoxHTML}
                        
                        <div style="height: 20px;"></div>
                    </div>

                    <div class="unidar-contract-footer">
                        <button class="btn btn-secondary" onclick="ContractManager.closeModal()" style="padding: 10px 32px; font-weight: 700; border-radius: 12px; margin-right: 8px;">Fermer</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            console.log('[ContractModal] Modal successfully injected');
            console.log('[ContractModal] Modal element:', modal);
            console.log('[ContractModal] Modal computed style display:', window.getComputedStyle(modal).display);
            console.log('[ContractModal] Modal computed style visibility:', window.getComputedStyle(modal).visibility);
            console.log('[ContractModal] Modal computed style opacity:', window.getComputedStyle(modal).opacity);
            console.log('[ContractModal] Modal computed style z-index:', window.getComputedStyle(modal).zIndex);

            // Force a reflow to ensure styles are applied
            void modal.offsetHeight;

            // Double-check modal is still in DOM after a brief moment
            setTimeout(() => {
                const checkModal = document.getElementById('unidar-contract-modal');
                if (checkModal) {
                    console.log('[ContractModal] Modal still in DOM after 100ms');
                } else {
                    console.error('[ContractModal] Modal was removed from DOM!');
                }
            }, 100);

            // Close on overlay click - but only after a small delay to prevent immediate removal
            let modalJustCreated = true;
            setTimeout(() => {
                modalJustCreated = false;
            }, 500);

            modal.addEventListener('click', (e) => {
                // Prevent closing if modal was just created
                if (modalJustCreated) {
                    console.log('[ContractModal] Ignoring click - modal just created');
                    return;
                }
                if (e.target === modal) {
                    console.log('[ContractModal] Overlay clicked, closing modal');
                    this.closeAllModals();
                }
            });

            // Prevent closing when clicking content
            const content = modal.querySelector('.unidar-contract-content');
            if (content) {
                content.addEventListener('click', (e) => {
                    e.stopPropagation();
                    console.log('[ContractModal] Content clicked, preventing close');
                });
            } else {
                console.warn('[ContractModal] Content element not found!');
            }

            // Setup Signature Pad if needed
            const padEl = document.getElementById('student-signature-pad');
            if (padEl) {
                setTimeout(() => {
                    try {
                        if (typeof UnidarSignaturePad === 'undefined') throw new Error('UnidarSignaturePad script missing');
                        const pad = new UnidarSignaturePad('student-signature-pad', { backgroundColor: '#ffffff' });
                        document.getElementById('btn-sign-contract').onclick = async () => {
                            if (pad.isEmpty()) return alert('Veuillez signer.');
                            showLoadingModal('Signature...');
                            try {
                                const apiBase = getContractApiBase();
                                const resp = await fetch(`${apiBase}/contracts.php?action=sign`, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ contract_id: contractId, signature: pad.toDataURL() }),
                                    credentials: 'include'
                                });
                                
                                const res = await resp.json();
                                
                                if (res.success) {
                                    hideLoadingModal();
                                    alert('✅ Contrat signé avec succès !');
                                    ContractManager.closeModal();
                                    
                                    // Redirect to payment modal immediately
                                    if (typeof PaymentManager !== 'undefined' && PaymentManager.showPaymentModal) {
                                        console.log('[ContractModal] Redirecting to payment modal for listing:', listingId, 'contract:', contractId);
                                        PaymentManager.showPaymentModal(listingId, contractId);
                                    } else {
                                        console.warn('[ContractModal] PaymentManager not found, reloading page');
                                        window.location.reload();
                                    }
                                } else {
                                    hideLoadingModal();
                                    alert('❌ Erreur: ' + (res.error || 'Erreur inconnue'));
                                }
                            } catch (e) {
                                hideLoadingModal();
                                alert('❌ Erreur de connexion: ' + e.message);
                            }
                        };
                    } catch (e) { console.error('[ContractModal] SigPad error:', e); }
                }, 400);
            }

        } catch (e) {
            console.error('[ContractModal] Error opening modal:', e);
            alert('Erreur lors de l\'ouverture: ' + e.message);
        }
    },

    async signContract(contractId, signatureData) {
        const apiBase = getContractApiBase();
        const response = await fetch(`${apiBase}/contracts.php?action=sign`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contract_id: contractId, signature: signatureData }),
            credentials: 'include'
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.error || 'Erreur lors de la signature');
        return result;
    },

    async uploadContractFile(contractId, file, studentName) {
        const formData = new FormData();
        formData.append('contract_file', file);
        formData.append('contract_id', contractId);
        formData.append('student_name', studentName);
        const apiBase = getContractApiBase();
        const response = await fetch(`${apiBase}/contracts.php?action=upload`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.error || 'Upload failed');
        return result;
    },

    closeModal() {
        console.log('[ContractModal] Closing');
        this.closeAllModals();
    },

    closeAllModals(skipLogging = false) {
        // Close loading modal first
        hideLoadingModal();

        // Close contract view modal
        const contractModal = document.getElementById('unidar-contract-modal');
        if (contractModal) {
            contractModal.remove();
            if (!skipLogging) console.log('[ContractModal] Removed unidar-contract-modal');
        }

        // Close setup modal
        const setupModal = document.getElementById('contract-setup-modal');
        if (setupModal) {
            setupModal.remove();
            if (!skipLogging) console.log('[ContractModal] Removed contract-setup-modal');
        }

        // Cleanup any remaining overlays (but be careful not to remove the one we just created)
        document.querySelectorAll('.unidar-contract-overlay, .modal-overlay.contract-modal-main').forEach(el => {
            // Don't remove if it's the modal we just created
            if (el.id !== 'unidar-contract-modal' && el.id !== 'contract-setup-modal' && el.id !== 'loading-modal') {
                el.remove();
                if (!skipLogging) console.log('[ContractModal] Removed overlay:', el.className);
            }
        });

        // Also remove any modal-overlay that might be a contract modal
        document.querySelectorAll('.modal-overlay').forEach(el => {
            if ((el.id === 'contract-setup-modal' || el.querySelector('#contractSetupForm')) && el.id !== 'contract-setup-modal' && el.id !== 'loading-modal') {
                el.remove();
                if (!skipLogging) console.log('[ContractModal] Removed contract setup overlay');
            }
        });
    },

    async terminateContract(contractId, reason = '') {
        try {
            showLoadingModal('Résiliation du contrat...');
            const apiBase = getContractApiBase();
            const response = await fetch(`${apiBase}/contracts.php?action=terminate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contract_id: contractId, reason: reason }),
                credentials: 'include'
            });
            const result = await response.json();
            hideLoadingModal();

            if (result.success) {
                showSuccessModal('Contrat résilié avec succès. Le logement est maintenant disponible.');
                if (window.loadUserContracts) window.loadUserContracts();
                // Reload page to update listing availability
                setTimeout(() => {
                    if (window.location.pathname.includes('listing-detail')) {
                        window.location.reload();
                    }
                }, 1500);
                return result;
            } else {
                showErrorModal(result.error || 'Erreur lors de la résiliation');
                return null;
            }
        } catch (error) {
            hideLoadingModal();
            console.error('Termination error:', error);
            showErrorModal('Erreur de connexion au serveur');
            return null;
        }
    },

    async requestTermination(contractId, reason = '') {
        try {
            showLoadingModal('Envoi de la demande de résiliation...');
            const apiBase = getContractApiBase();
            const response = await fetch(`${apiBase}/contracts.php?action=request-termination`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contract_id: contractId, reason: reason }),
                credentials: 'include'
            });
            const result = await response.json();
            hideLoadingModal();

            if (result.success) {
                showSuccessModal('Demande de résiliation envoyée au propriétaire. En attente d\'approbation.');
                if (window.loadUserContracts) window.loadUserContracts();
                return result;
            } else {
                showErrorModal(result.error || 'Erreur lors de la demande');
                return null;
            }
        } catch (error) {
            hideLoadingModal();
            console.error('Termination request error:', error);
            showErrorModal('Erreur de connexion au serveur');
            return null;
        }
    },

    async checkExpiredContracts() {
        try {
            const apiBase = getContractApiBase();
            const response = await fetch(`${apiBase}/contracts.php?action=check-expired`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include'
            });
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Error checking expired contracts:', error);
            return { success: false, error: error.message };
        }
    }
};

// Expose ContractManager globally
window.ContractManager = ContractManager;

// Also create global wrapper functions for inline onclick handlers
window.showContractSetupModal = function (listingId) {
    console.log('[Global] showContractSetupModal called with listingId:', listingId);
    if (typeof ContractManager !== 'undefined' && ContractManager.showContractSetupModal) {
        ContractManager.showContractSetupModal(listingId);
    } else {
        console.error('[Global] ContractManager not available!');
        alert('Erreur: ContractManager non disponible. Veuillez recharger la page.');
    }
};

// Debug: Log when ContractManager is loaded
console.log('[ContractManager] Loaded and available on window.ContractManager:', typeof window.ContractManager !== 'undefined');

// Card Validation Utilities
const CardValidator = {
    // Luhn algorithm for card number validation
    luhnCheck(cardNumber) {
        const digits = cardNumber.replace(/\D/g, '');
        if (digits.length < 13 || digits.length > 19) return false;

        let sum = 0;
        let isEven = false;

        for (let i = digits.length - 1; i >= 0; i--) {
            let digit = parseInt(digits[i], 10);
            if (isEven) {
                digit *= 2;
                if (digit > 9) digit -= 9;
            }
            sum += digit;
            isEven = !isEven;
        }
        return sum % 10 === 0;
    },

    // Format card number with spaces
    formatCardNumber(value) {
        const v = value.replace(/\D/g, '').substring(0, 16);
        const parts = [];
        for (let i = 0; i < v.length; i += 4) {
            parts.push(v.substring(i, i + 4));
        }
        return parts.join(' ');
    },

    // Detect card brand from number
    getCardBrand(cardNumber) {
        const num = cardNumber.replace(/\D/g, '');
        if (/^4/.test(num)) return { brand: 'Visa', icon: '💳' };
        if (/^5[1-5]/.test(num)) return { brand: 'Mastercard', icon: '💳' };
        if (/^3[47]/.test(num)) return { brand: 'Amex', icon: '💳' };
        if (/^6(?:011|5)/.test(num)) return { brand: 'Discover', icon: '💳' };
        return { brand: '', icon: '💳' };
    },

    // Format expiry date
    formatExpiry(value) {
        const v = value.replace(/\D/g, '').substring(0, 4);
        if (v.length >= 2) {
            return v.substring(0, 2) + '/' + v.substring(2);
        }
        return v;
    },

    // Validate expiry date
    isValidExpiry(expiry) {
        const match = expiry.match(/^(0[1-9]|1[0-2])\/([0-9]{2})$/);
        if (!match) return false;

        const month = parseInt(match[1], 10);
        const year = parseInt('20' + match[2], 10);
        const now = new Date();
        const expDate = new Date(year, month);

        return expDate > now;
    }
};
window.CardValidator = CardValidator;

// Payment Management Functions
const PaymentManager = {
    async calculatePaymentAmounts(listingId) {
        try {
            const apiBase = getContractApiBase();
            const response = await fetch(`${apiBase}/contracts.php?action=calculate-payment&listing_id=${listingId}`, {
                method: 'GET',
                credentials: 'include'
            });

            const result = await response.json();

            if (result.success) {
                return result;
            } else {
                showErrorModal(result.error || 'Erreur lors du calcul du paiement');
                return null;
            }
        } catch (error) {
            console.error('Payment calculation error:', error);
            showErrorModal('Erreur de connexion au serveur');
            return null;
        }
    },

    showPaymentModal(listingId, contractId) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 850px; padding: var(--space-lg);">
                <div class="modal-header" style="margin-bottom: var(--space-md);">
                    <h3>Paiement Sécurisé</h3>
                    <button class="modal-close" onclick="PaymentManager.closeModal(this)">&times;</button>
                </div>
                
                <div class="modal-body" style="padding: 0;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-xl);">
                        <!-- Left Column: Card & Summary -->
                        <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                            <div class="card-preview-container" style="margin: 0;">
                                <div class="virtual-card" id="virtual-card" style="width: 100%; aspect-ratio: 1.58; height: auto; transform: scale(1);">
                                    <div class="card-brand-logo" id="v-card-brand">💳</div>
                                    <div class="card-chip"></div>
                                    <div class="card-number-display" id="v-card-number" style="font-size: 1.2rem; margin-top: 15px;">•••• •••• •••• ••••</div>
                                    <div class="card-bottom-info">
                                        <div class="card-holder-display">
                                            <div class="card-label">Titulaire</div>
                                            <div class="card-holder-name" id="v-card-holder">NOM PRENOM</div>
                                        </div>
                                        <div class="card-expiry-display-container">
                                            <div class="card-label">Expire</div>
                                            <div class="card-expiry-display" id="v-card-expiry">MM/YY</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-summary" style="background: var(--color-surface-50); padding: var(--space-md); border-radius: var(--radius-xl); border: 1px solid var(--color-surface-100);">
                                <div class="payment-breakdown" style="font-size: 0.9rem;">
                                    <div class="flex justify-between mb-xs">
                                        <span class="text-muted">Loyer Mensuel:</span>
                                        <span id="monthlyRent" class="font-bold">--</span>
                                    </div>
                                    <div class="flex justify-between mb-xs">
                                        <span class="text-muted">Commission (5%):</span>
                                        <span id="commissionAmount" class="font-bold">--</span>
                                    </div>
                                    <div class="flex justify-between pt-sm mt-sm" style="border-top: 1px dashed var(--color-surface-200);">
                                        <span class="font-bold">Total:</span>
                                        <span id="totalAmount" class="font-bold text-brand" style="font-size: 1.1rem;">--</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Form -->
                        <div style="display: flex; flex-direction: column; justify-content: space-between;">
                            <div class="payment-methods" id="paymentMethodsContainer">
                                <div class="flex gap-sm mb-md">
                                    <button class="btn btn-secondary flex-1 active-tab" id="tab-card" onclick="PaymentManager.switchTab('card')" style="padding: 8px; font-size: 0.85rem;">
                                        💳 Carte
                                    </button>
                                    <button class="btn btn-secondary flex-1" id="tab-transfer" onclick="PaymentManager.switchTab('transfer')" style="padding: 8px; font-size: 0.85rem;">
                                        🏦 Virement
                                    </button>
                                </div>

                                <div id="card-form">
                                    <div class="form-group mb-sm">
                                        <label class="form-label" style="font-size: 0.75rem;">Nom sur la Carte</label>
                                        <input type="text" id="cc-name" class="form-input" placeholder="NOM COMPLET" style="padding: 8px; font-size: 0.9rem;">
                                    </div>

                                    <div class="form-group mb-sm">
                                        <label class="form-label" style="font-size: 0.75rem;">Numéro de Carte</label>
                                        <input type="text" id="cc-number" class="form-input" placeholder="•••• •••• •••• ••••" maxlength="19" style="padding: 8px; font-size: 1rem; font-family: monospace;">
                                    </div>

                                    <div class="grid-2 gap-sm mb-md">
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.75rem;">Expiration</label>
                                            <input type="text" id="cc-expiry" class="form-input" placeholder="MM/YY" maxlength="5" style="padding: 8px; font-size: 0.9rem; text-align: center;">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.75rem;">CVC</label>
                                            <input type="password" id="cc-cvv" class="form-input" placeholder="•••" maxlength="4" style="padding: 8px; font-size: 0.9rem; text-align: center;">
                                        </div>
                                    </div>
                                </div>

                                <div id="transfer-info" style="display: none; font-size: 0.85rem;">
                                    <div class="alert alert-info p-sm" style="margin: 0;">
                                        <p class="mb-xs"><strong>IBAN:</strong> TN59...7890</p>
                                        <p class="mb-xs"><strong>Banque:</strong> BIAT</p>
                                        <p class="mb-0"><strong>Ref:</strong> <span class="text-brand">CONTRAT-${contractId}</span></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="payment-processing" style="display: none; text-align: center; padding: var(--space-md) 0;">
                                <div style="width: 40px; height: 40px; margin: 0 auto 10px; border: 3px solid var(--color-surface-100); border-top-color: var(--color-brand); border-radius: 50%; animation: spin 1s linear infinite;"></div>
                                <h5 id="processing-text" class="m-0">Traitement...</h5>
                            </div>

                            <div style="margin-top: auto;">
                                <button id="btn-confirm-payment" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: 700;" onclick="PaymentManager.processPayment(${contractId})">
                                    🔐 Payer Maintenant
                                </button>
                                <p class="text-center text-tiny text-muted mt-sm" style="margin-bottom: 0;">
                                    🛡️ Sécurisé par UNIDAR Pay
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Add card input formatting listeners
        PaymentManager.initCardInputs();
        PaymentManager.loadPaymentDetails(listingId);

    },

    initCardInputs() {
        const ccName = document.getElementById('cc-name');
        const ccNumber = document.getElementById('cc-number');
        const ccExpiry = document.getElementById('cc-expiry');
        const cardBrandStatus = document.getElementById('card-brand-status');
        const cardError = document.getElementById('card-error');

        // Virtual card elements
        const vCardNumber = document.getElementById('v-card-number');
        const vCardHolder = document.getElementById('v-card-holder');
        const vCardExpiry = document.getElementById('v-card-expiry');
        const vCardBrand = document.getElementById('v-card-brand');

        if (ccName) {
            ccName.addEventListener('input', (e) => {
                const val = e.target.value.toUpperCase();
                if (vCardHolder) vCardHolder.textContent = val || 'NOM PRENOM';
            });
        }

        if (ccNumber) {
            ccNumber.addEventListener('input', (e) => {
                const formatted = CardValidator.formatCardNumber(e.target.value);
                e.target.value = formatted;

                // Sync virtual card
                if (vCardNumber) {
                    let masked = formatted || '•••• •••• •••• ••••';
                    // Fill remaining dots if shorter
                    const dotsNeeded = 19 - masked.length;
                    if (dotsNeeded > 0) {
                        const dots = '•••• •••• •••• ••••'.substring(masked.length);
                        masked += dots;
                    }
                    vCardNumber.textContent = masked;
                }

                // Update card brand
                const brand = CardValidator.getCardBrand(formatted);
                if (cardBrandStatus) {
                    cardBrandStatus.textContent = brand.brand ? brand.brand : '';
                }
                if (vCardBrand) {
                    vCardBrand.textContent = brand.brand ? brand.icon : '💳';
                    // Visual feedback: subtly change card gradient based on brand
                    const vCard = document.getElementById('virtual-card');
                    if (vCard) {
                        if (brand.brand === 'Visa') vCard.style.background = 'linear-gradient(135deg, #1a365d, #2c5282)';
                        else if (brand.brand === 'Mastercard') vCard.style.background = 'linear-gradient(135deg, #4a5568, #2d3748)';
                        else vCard.style.background = 'linear-gradient(135deg, #1e293b, #0f172a)';
                    }
                }

                // Validate on blur
                const digits = formatted.replace(/\D/g, '');
                if (digits.length >= 13) {
                    if (CardValidator.luhnCheck(digits)) {
                        ccNumber.style.borderColor = 'var(--color-success)';
                        if (cardError) cardError.style.display = 'none';
                    } else {
                        ccNumber.style.borderColor = 'var(--color-error)';
                        if (cardError) {
                            cardError.textContent = 'Numéro de carte invalide';
                            cardError.style.display = 'block';
                        }
                    }
                } else {
                    ccNumber.style.borderColor = '';
                    if (cardError) cardError.style.display = 'none';
                }
            });
        }

        if (ccExpiry) {
            ccExpiry.addEventListener('input', (e) => {
                e.target.value = CardValidator.formatExpiry(e.target.value);
                if (vCardExpiry) vCardExpiry.textContent = e.target.value || 'MM/YY';

                if (e.target.value.length === 5) {
                    if (CardValidator.isValidExpiry(e.target.value)) {
                        ccExpiry.style.borderColor = 'var(--color-success)';
                    } else {
                        ccExpiry.style.borderColor = 'var(--color-error)';
                    }
                } else {
                    ccExpiry.style.borderColor = '';
                }
            });
        }
    },

    async loadPaymentDetails(listingId) {
        const result = await PaymentManager.calculatePaymentAmounts(listingId);
        if (result) {
            document.getElementById('monthlyRent').textContent = formatCurrency(result.rent);
            document.getElementById('commissionAmount').textContent = formatCurrency(result.commission);
            document.getElementById('totalAmount').textContent = formatCurrency(result.total);
        }
    },

    currentMethod: 'card',

    switchTab(method) {
        this.currentMethod = method;
        document.getElementById('card-form').style.display = method === 'card' ? 'block' : 'none';
        document.getElementById('transfer-info').style.display = method === 'transfer' ? 'block' : 'none';

        document.getElementById('tab-card').classList.toggle('active-tab', method === 'card');
        document.getElementById('tab-transfer').classList.toggle('active-tab', method === 'transfer');

        // Form styling for buttons (fallback since we use vanilla CSS)
        document.getElementById('tab-card').style.background = method === 'card' ? 'var(--color-brand)' : '';
        document.getElementById('tab-card').style.color = method === 'card' ? 'white' : '';
        document.getElementById('tab-transfer').style.background = method === 'transfer' ? 'var(--color-brand)' : '';
        document.getElementById('tab-transfer').style.color = method === 'transfer' ? 'white' : '';
    },

    async processPayment(contractId) {
        if (this.currentMethod === 'card') {
            const cc = document.getElementById('cc-number').value;
            const exp = document.getElementById('cc-expiry').value;
            const cvv = document.getElementById('cc-cvv').value;
            const name = document.getElementById('cc-name').value;

            if (!cc || !exp || !cvv || !name) {
                showErrorModal('Veuillez remplir tous les champs de la carte.');
                return;
            }

            // Validate card number with Luhn
            const cardDigits = cc.replace(/\D/g, '');
            if (cardDigits.length < 13) {
                showErrorModal('Veuillez entrer un numéro de carte complet.');
                return;
            }

            if (!CardValidator.luhnCheck(cardDigits)) {
                showErrorModal('Le numéro de carte est invalide. Veuillez vérifier.');
                return;
            }

            if (!CardValidator.isValidExpiry(exp)) {
                showErrorModal('La date d\'expiration est invalide ou dépassée.');
                return;
            }
        } else {
            // Virement logic - simply close and show success info
            showSuccessModal('Demande de virement enregistrée. Votre contrat sera activé dès réception des fonds.');
            PaymentManager.closeModal();
            loadUserContracts();
            return;
        }

        // Realistic processing steps
        const steps = [
            { text: 'Identification de la banque...', progress: 15 },
            { text: 'Chiffrement de la transaction...', progress: 35 },
            { text: 'Attente d\'autorisation bancaire...', progress: 60 },
            { text: 'Validation du contrat...', progress: 85 },
            { text: 'Paiement autorisé !', progress: 100 }
        ];

        document.getElementById('paymentMethodsContainer').style.display = 'none';
        document.getElementById('btn-confirm-payment').style.display = 'none';
        document.getElementById('payment-processing').style.display = 'block';

        // Run multi-step simulation
        try {
            const progress = document.getElementById('payment-progress');
            const text = document.getElementById('processing-text');
            const substep = document.getElementById('processing-substep');

            for (const step of steps) {
                if (text) text.textContent = step.text;
                if (progress) progress.style.width = step.progress + '%';

                // Add sub-step detail for realism
                if (substep) {
                    if (step.progress === 15) substep.textContent = 'Connexion sécurisée TLS 1.3...';
                    if (step.progress === 35) substep.textContent = 'Cryptage AES-256...';
                    if (step.progress === 60) substep.textContent = 'Vérification 3D Secure...';
                    if (step.progress === 85) substep.textContent = 'Enregistrement de l\'acte...';
                }

                await new Promise(r => setTimeout(r, 800 + Math.random() * 500));
            }

            const apiBase = getContractApiBase();
            const response = await fetch(`${apiBase}/contracts.php?action=process-payment`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    contract_id: contractId,
                    payment_method: this.currentMethod
                }),
                credentials: 'include'
            });

            const result = await response.json();

            if (result.success) {
                // Final Success Animation in the same modal
                const container = document.getElementById('payment-processing');
                if (container) {
                    container.innerHTML = `
                        <div class="success-check">✓</div>
                        <h3 class="mb-sm">Paiement Réussi !</h3>
                        <p class="text-muted">Votre contrat est maintenant actif.</p>
                        <div style="margin-top: 20px;">
                             <p class="text-tiny text-muted">Transaction: ${result.transaction_id || 'TRX-SUCCESS'}</p>
                        </div>
                    `;
                }

                setTimeout(() => {
                    PaymentManager.closeModal();
                    loadUserContracts();
                    showSuccessModal('Félicitations ! Votre paiement a été traité et votre contrat est désormais actif.');
                }, 2500);
            } else {
                showErrorModal(result.error || 'Échec du paiement');
                document.getElementById('paymentMethodsContainer').style.display = 'block';
                document.getElementById('btn-confirm-payment').style.display = 'block';
                document.getElementById('payment-processing').style.display = 'none';
            }
        } catch (error) {
            console.error('Payment error:', error);
            showErrorModal('Erreur de connexion lors du paiement');
            if (document.getElementById('paymentMethodsContainer')) {
                document.getElementById('paymentMethodsContainer').style.display = 'block';
                document.getElementById('btn-confirm-payment').style.display = 'block';
                document.getElementById('payment-processing').style.display = 'none';
            }
        }
    },

    showPaymentSuccess(paymentResult) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay success-modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 400px; text-align: center;">
                <div class="modal-body">
                    <div style="font-size: 4rem; margin-bottom: var(--space-md);">✅</div>
                    <h3>Paiement Réussi!</h3>
                    <p>Votre contrat a été signé et le paiement a été traité avec succès.</p>
                    <p><strong>Transaction ID:</strong> ${paymentResult.transaction_id}</p>
                    <p><strong>Montant:</strong> ${formatCurrency(paymentResult.amount)}</p>
                    <button class="btn btn-primary" onclick="window.location.href='user-dashboard.html'" style="margin-top: var(--space-md);">
                        Voir mes contrats
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    },

    closeModal(btn) {
        console.log('[PaymentModal] Closing modal');
        if (btn) {
            const modal = btn.closest('.modal-overlay');
            if (modal) {
                modal.remove();
                return;
            }
        }
        const modal = document.querySelector('.modal-overlay');
        if (modal) {
            modal.remove();
        }
    }
};
window.PaymentManager = PaymentManager;

// Helper functions
function showLoadingModal(message = 'Chargement...') {
    // Remove any existing loading modals first
    hideLoadingModal();

    const modal = document.createElement('div');
    modal.id = 'loading-modal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 300px; text-align: center;">
            <div class="modal-body">
                <div style="font-size: 2rem; margin-bottom: var(--space-md);">⏳</div>
                <p>${message}</p>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    console.log('[LoadingModal] Loading modal shown:', message);
}

function hideLoadingModal() {
    // Remove by ID first (most reliable)
    const loadingModal = document.getElementById('loading-modal');
    if (loadingModal) {
        loadingModal.remove();
        console.log('[LoadingModal] Removed loading modal by ID');
        return;
    }

    // Fallback: find by content
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        const hasLoadingContent = modal.querySelector('div[style*="⏳"]') ||
            modal.textContent.includes('Préparation') ||
            modal.textContent.includes('Chargement') ||
            modal.textContent.includes('Signature');
        if (hasLoadingContent && !modal.id) {
            modal.remove();
            console.log('[LoadingModal] Removed loading modal by content');
        }
    });
}

function showErrorModal(message) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Erreur</h3>
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color: var(--color-error);">${message}</p>
                <button class="btn btn-primary" onclick="this.closest('.modal-overlay').remove()" style="margin-top: var(--space-md);">
                    OK
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
}

function showSuccessModal(message) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-body">
                <div style="font-size: 4rem; margin-bottom: var(--space-md);">✅</div>
                <h3>Succès!</h3>
                <p>${message}</p>
                <button class="btn btn-primary" onclick="this.closest('.modal-overlay').remove()" style="margin-top: var(--space-md);">
                    OK
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-TN', {
        style: 'currency',
        currency: 'TND'
    }).format(amount);
}

// Global functions for onclick handlers
window.showLoadingModal = showLoadingModal;
window.hideLoadingModal = hideLoadingModal;
window.showErrorModal = showErrorModal;
window.showSuccessModal = showSuccessModal;
window.formatCurrency = formatCurrency;

// User Dashboard Functions
async function loadUserContracts() {
    const container = document.getElementById('contractsList');
    if (!container) return;

    // Register action listener exactly once via event delegation
    if (!container.dataset.listener) {
        container.addEventListener('click', (e) => {
            // Find the button by traversing up the DOM tree
            let btn = e.target.closest('[data-contract-action]');

            // If closest didn't work, try finding the button in parent elements
            if (!btn) {
                let element = e.target;
                while (element && element !== container) {
                    if (element.hasAttribute && element.hasAttribute('data-contract-action')) {
                        btn = element;
                        break;
                    }
                    element = element.parentElement;
                }
            }

            if (btn) {
                console.log(`[ContractAction] Delegated click: ${btn.dataset.contractAction} for ${btn.dataset.contractId}`);
                e.preventDefault();
                e.stopPropagation();

                const action = btn.dataset.contractAction;
                const listingId = btn.dataset.listingId || btn.getAttribute('data-listing-id');
                const contractId = btn.dataset.contractId || btn.getAttribute('data-contract-id');
                const url = btn.dataset.url || btn.getAttribute('data-url');
                const role = btn.dataset.role || btn.getAttribute('data-role') || 'student';
                const status = btn.dataset.status || btn.getAttribute('data-status');

                console.log(`[ContractAction] Action: ${action}, ContractID: ${contractId}, ListingID: ${listingId}, URL: ${url}, Role: ${role}, Status: ${status}`);

                if (action === 'view') {
                    if (!contractId || !url) {
                        console.error('[ContractAction] Missing required data for view action', { contractId, url, listingId });
                        showErrorModal('Erreur: Données du contrat manquantes');
                        return;
                    }

                    try {
                        ContractManager.showContractModal(
                            listingId || 0,
                            url,
                            null,
                            contractId,
                            role,
                            status
                        );
                    } catch (error) {
                        console.error('[ContractAction] Error showing contract modal:', error);
                        showErrorModal('Erreur lors de l\'ouverture du contrat: ' + error.message);
                    }
                } else if (action === 'pay') {
                    if (!contractId || !listingId) {
                        console.error('[ContractAction] Missing required data for pay action', { contractId, listingId });
                        showErrorModal('Erreur: Données du paiement manquantes');
                        return;
                    }

                    try {
                        PaymentManager.showPaymentModal(listingId, contractId);
                    } catch (error) {
                        console.error('[ContractAction] Error showing payment modal:', error);
                        showErrorModal('Erreur lors de l\'ouverture du paiement: ' + error.message);
                    }
                }
            }
        }, true); // Use capture phase for better reliability
        container.dataset.listener = 'true';
        console.log('[ContractAction] Event listener registered on container with capture phase');
    } else {
        console.log('[ContractAction] Event listener already registered, skipping');
    }

    try {
        container.innerHTML = `
            <div class="text-center text-muted p-xl">
                <div class="badge-dot" style="width: 20px; height: 20px; margin: 0 auto 10px;"></div>
                <p>Chargement des contrats...</p>
            </div>
        `;

        const apiBase = getContractApiBase();
        const response = await fetch(`${apiBase}/contracts.php?action=user-contracts`, {
            method: 'GET',
            credentials: 'include'
        });

        const result = await response.json();

        if (!result.success || !result.contracts) {
            container.innerHTML = '<div class="empty-state card p-xl text-center"><p class="text-muted">Erreur lors du chargement des contrats.</p></div>';
            return;
        }

        const userRole = result.role || 'student';

        // FILTERING LOGIC
        // Students should only see signed or active contracts (hide drafts)
        // Admin shows only active contracts (paid/active/completed)
        const filteredContracts = result.contracts.filter(c => {
            if (userRole === 'student') {
                // Return only signed, paid or active contracts
                return ['active', 'paid', 'completed', 'signed_by_student', 'signed_by_owner', 'pending_payment'].includes(c.status);
            }
            // Admin and Owner: show ONLY active contracts (exclude drafts, pending, terminated, expired)
            return ['active', 'paid', 'completed'].includes(c.status);
        });

        if (filteredContracts.length === 0) {
            container.innerHTML = `
                <div class="empty-state card p-xl text-center" style="grid-column: 1/-1;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📄</div>
                    <p class="text-muted">Aucun contrat signé pour le moment.</p>
                    ${userRole === 'student' ? '<p class="text-small">Générez un contrat depuis une annonce pour commencer.</p>' : ''}
                </div>
            `;
            return;
        }

        container.innerHTML = filteredContracts.map((contract, index) => {
            const delay = (index % 6) * 0.1;
            const statusBadge = getStatusBadge(contract.status);
            const date = new Date(contract.created_at).toLocaleDateString('fr-FR', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            // Construct Download/View URL
            const downloadUrl = contract.contract_url || `${apiBase}/contracts.php?action=download&contract_id=${contract.id}`;

            // Build actions based on status and role
            let actions = '';

            if (userRole === 'admin') {
                // Admin: Simple direct download/view in new tab (bypasses modal as requested)
                actions += `
                    <a href="${downloadUrl}" target="_blank" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center; text-decoration: none; padding-top: 8px;">
                        📄 Télécharger
                    </a>
                `;
            } else {
                // Student/Owner: Show modal with tailored options
                // Use both data attributes AND store data globally for onclick fallback
                const contractId = contract.id;
                const listingId = contract.listing_id || 0;
                const contractStatus = contract.status || '';

                // Store contract data globally for the onclick handler (safer than inline)
                if (!window._contractData) window._contractData = {};
                window._contractData[contractId] = {
                    listingId: listingId,
                    url: downloadUrl,
                    role: userRole,
                    status: contractStatus
                };

                actions += `
                    <button type="button" class="btn btn-secondary btn-sm contract-view-btn" style="flex: 1; cursor: pointer; pointer-events: auto;" 
                        data-contract-action="view"
                        data-contract-id="${contractId}"
                        data-listing-id="${listingId}"
                        data-url="${downloadUrl.replace(/"/g, '&quot;')}"
                        data-role="${userRole}"
                        data-status="${contractStatus}">
                        👁️ Voir
                    </button>
                `;

                // Extra "Payer" button for students if needed
                if (userRole === 'student' && ['signed_by_student', 'signed_by_owner', 'pending_payment'].includes(contract.status) && !['paid', 'active', 'completed'].includes(contract.status)) {
                    actions += `
                        <button type="button" class="btn btn-primary btn-sm contract-pay-btn" style="flex: 1; cursor: pointer;" 
                            data-contract-action="pay"
                            data-contract-id="${contractId}"
                            data-listing-id="${listingId}"
                            onclick="if(typeof PaymentManager !== 'undefined') { PaymentManager.showPaymentModal('${listingId}', '${contractId}'); } else { console.error('PaymentManager not available'); }">
                            💳 Payer
                        </button>
                    `;
                }

                // Terminate button for owners on active contracts
                if (userRole === 'owner' && ['paid', 'active', 'completed', 'signed_by_both'].includes(contract.status)) {
                    actions += `
                        <button type="button" class="btn btn-error btn-sm contract-terminate-btn" style="flex: 1; cursor: pointer; background: #dc3545; color: white; border: none;" 
                            data-contract-id="${contractId}"
                            onclick="if(confirm('Êtes-vous sûr de vouloir résilier ce contrat ? Le logement redeviendra disponible.')) { ContractManager.terminateContract('${contractId}'); }">
                            🚫 Résilier
                        </button>
                    `;
                }

                // Request termination button for students on active contracts
                if (userRole === 'student' && ['paid', 'active', 'completed', 'signed_by_both'].includes(contract.status)) {
                    actions += `
                        <button type="button" class="btn btn-warning btn-sm contract-request-terminate-btn" style="flex: 1; cursor: pointer; background: #ffc107; color: #000; border: none;" 
                            data-contract-id="${contractId}"
                            onclick="if(confirm('Voulez-vous demander la résiliation de ce contrat au propriétaire ?')) { ContractManager.requestTermination('${contractId}'); }">
                            📝 Demander Résiliation
                        </button>
                    `;
                }
            }

            // Determine party display based on role
            let partyName = contract.other_party_name || 'Partie Adverse';
            let partyRole = userRole === 'owner' ? 'Étudiant' : 'Propriétaire';

            if (userRole === 'admin') {
                partyName = `${contract.student_name || 'N/A'}`;
                partyRole = `Proprio: ${contract.owner_name || 'N/A'}`;
            }

            return `
            <article class="p-md bg-white rounded-xl hover-lift border-surface-100 border-solid border-1 fade-in-up" 
                 style="animation-delay: ${delay}s; transition: all 0.3s ease; border: 1px solid var(--color-surface-100); margin-bottom: var(--space-sm);">
                <div class="flex justify-between items-start mb-xs">
                    <div class="flex-column">
                         <h4 class="m-0" style="font-size: 0.95rem; font-weight: 800; color: var(--color-surface-900);">#${contract.id} - ${partyName}</h4>
                         <span class="text-tiny text-muted uppercase font-bold tracking-wider" style="font-size: 0.65rem;">${partyRole}</span>
                    </div>
                    ${statusBadge}
                </div>
                <div class="flex justify-between items-center mt-sm pt-sm" style="border-top: 1px solid var(--color-surface-50);">
                    <div class="flex-column">
                        <span class="text-tiny font-bold color-brand" style="font-size: 0.7rem;">${date}</span>
                    </div>
                    <div class="flex gap-xs">
                        ${actions}
                    </div>
                </div>
            </article>
            `;
        }).join('');

        // Verify ContractManager is available
        if (typeof ContractManager === 'undefined') {
            console.error('[ContractButton] ContractManager is not defined! Make sure contracts.js is loaded.');
            container.innerHTML += '<div class="alert alert-error">Erreur: ContractManager non disponible. Veuillez recharger la page.</div>';
        }

        // Attach direct event listeners immediately after setting innerHTML
        // This ensures buttons work even if event delegation has issues
        requestAnimationFrame(() => {
            const viewButtons = container.querySelectorAll('.contract-view-btn, [data-contract-action="view"]');
            const payButtons = container.querySelectorAll('.contract-pay-btn, [data-contract-action="pay"]');

            console.log(`[ContractButton] Found ${viewButtons.length} view buttons and ${payButtons.length} pay buttons`);
            console.log('[ContractButton] ContractManager available:', typeof ContractManager !== 'undefined');
            console.log('[ContractButton] PaymentManager available:', typeof PaymentManager !== 'undefined');

            viewButtons.forEach((btn, index) => {
                // Remove any existing onclick to avoid conflicts
                btn.removeAttribute('onclick');

                // Define click handler function
                const clickHandler = function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('[ContractButton] View button clicked directly', this);
                    console.log('[ContractButton] Button element:', this);
                    console.log('[ContractButton] Event:', e);

                    const listingId = this.dataset.listingId || this.getAttribute('data-listing-id') || 0;
                    const contractId = this.dataset.contractId || this.getAttribute('data-contract-id');
                    const url = this.dataset.url || this.getAttribute('data-url');
                    const role = this.dataset.role || this.getAttribute('data-role') || 'student';
                    const status = this.dataset.status || this.getAttribute('data-status');

                    console.log('[ContractButton] View button data:', { contractId, listingId, url, role, status });

                    if (typeof ContractManager === 'undefined') {
                        console.error('[ContractButton] ContractManager is not defined!');
                        alert('Erreur: ContractManager non disponible. Veuillez recharger la page.');
                        return;
                    }

                    if (contractId && url) {
                        try {
                            ContractManager.showContractModal(listingId, url, null, contractId, role, status);
                        } catch (error) {
                            console.error('[ContractButton] Error in showContractModal:', error);
                            alert('Erreur lors de l\'ouverture: ' + error.message);
                        }
                    } else {
                        console.error('[ContractButton] Missing data attributes', { contractId, url });
                        if (typeof showErrorModal !== 'undefined') {
                            showErrorModal('Erreur: Données du contrat manquantes');
                        } else {
                            alert('Erreur: Données du contrat manquantes');
                        }
                    }
                };

                // Add listener with both capture and bubble phases
                btn.addEventListener('click', clickHandler, true);
                btn.addEventListener('click', clickHandler, false);

                // Also add mousedown as backup
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    clickHandler.call(this, e);
                });

                console.log(`[ContractButton] Attached handlers to view button ${index + 1}`);
            });

            payButtons.forEach((btn) => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('[ContractButton] Pay button clicked directly', this);

                    const listingId = this.dataset.listingId || this.getAttribute('data-listing-id');
                    const contractId = this.dataset.contractId || this.getAttribute('data-contract-id');

                    console.log('[ContractButton] Pay button data:', { contractId, listingId });

                    if (typeof PaymentManager === 'undefined') {
                        console.error('[ContractButton] PaymentManager is not defined!');
                        alert('Erreur: PaymentManager non disponible. Veuillez recharger la page.');
                        return;
                    }

                    if (contractId && listingId) {
                        try {
                            PaymentManager.showPaymentModal(listingId, contractId);
                        } catch (error) {
                            console.error('[ContractButton] Error in showPaymentModal:', error);
                            alert('Erreur lors de l\'ouverture: ' + error.message);
                        }
                    } else {
                        console.error('[ContractButton] Missing data attributes', { contractId, listingId });
                        if (typeof showErrorModal !== 'undefined') {
                            showErrorModal('Erreur: Données du paiement manquantes');
                        } else {
                            alert('Erreur: Données du paiement manquantes');
                        }
                    }
                }, true); // Use capture phase
            });
        });

    } catch (error) {
        console.error('Error loading contracts:', error);
        container.innerHTML = '<div class="alert alert-error">Erreur lors du chargement des contrats.</div>';
    }
}

function getStatusBadge(status) {
    let color = 'info';
    let label = status;

    switch (status) {
        case 'generated':
        case 'draft':
            color = 'warning';
            label = 'Brouillon';
            break;
        case 'signed_by_student':
            color = 'info';
            label = 'Signé (Etud.)';
            break;
        case 'signed_by_owner':
            color = 'info';
            label = 'Signé (Propria.)';
            break;
        case 'pending_payment':
            color = 'brand';
            label = 'À Payer';
            break;
        case 'paid':
        case 'active':
        case 'completed':
            color = 'success';
            label = 'Actif';
            break;
        case 'terminated':
        case 'expired':
            color = 'white';
            label = 'Terminé';
            break;
        case 'signed':
            color = 'info';
            label = 'Signé';
            break;
        default:
            label = status.replace(/_/g, ' ');
    }

    return `<span class="badge badge-${color}" style="text-transform: capitalize;">${label}</span>`;
}

window.loadUserContracts = loadUserContracts;