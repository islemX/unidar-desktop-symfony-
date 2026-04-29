/**
 * Map Utilities for UNIDAR
 * Provides map initialization, route calculation, and faculty management
 */

const MapUtils = {
    map: null,
    listingMarker: null,
    facultyMarker: null,
    routeControl: null,
    faculties: [],

    /**
     * Initialize map for listing detail page
     */
    initListingMap: function (containerId, lat, lng, listingData) {
        if (this.map) {
            this.map.remove();
        }

        this.map = L.map(containerId).setView([lat, lng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(this.map);

        // Add custom marker for listing
        const customIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="background: var(--color-brand); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); border: 3px solid white;">🏠</div>',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });

        this.listingMarker = L.marker([lat, lng], { icon: customIcon })
            .addTo(this.map)
            .bindPopup(`
                <div style="max-width: 200px;">
                    <strong style="font-size: 14px;">${listingData.title}</strong><br>
                    <span style="color: var(--color-brand); font-weight: bold; font-size: 16px;">${listingData.price} TND/mois</span><br>
                    <span style="font-size: 12px; color: #666;">${listingData.address}</span>
                </div>
            `);

        // Force map to resize properly
        setTimeout(() => {
            this.map.invalidateSize();
        }, 100);

        return this.map;
    },

    /**
     * Load all faculties from API
     */
    loadFaculties: async function () {
        try {
            const response = await fetch('/unidar/backend/api/faculties.php');
            const data = await response.json();

            if (data.success) {
                this.faculties = data.faculties;
                return this.faculties;
            } else {
                console.error('Failed to load faculties:', data.message);
                return [];
            }
        } catch (error) {
            console.error('Error loading faculties:', error);
            return [];
        }
    },

    /**
     * Calculate and display route from faculty to listing
     */
    calculateRoute: async function (fromLat, fromLng, toLat, toLng, facultyName, profile = 'driving') {
        console.log('=== CALCULATE ROUTE START ===');
        console.log('Profile:', profile);
        console.log('From:', fromLat, fromLng, 'To:', toLat, toLng);

        // Store current profile
        this.currentProfile = profile;

        // Remove previous routes
        this.clearRoutes();

        // Remove previous faculty marker if exists
        if (this.facultyMarker) {
            this.map.removeLayer(this.facultyMarker);
        }

        // Add faculty marker
        const facultyIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="background: #4CAF50; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); border: 3px solid white;">🎓</div>',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });

        this.facultyMarker = L.marker([fromLat, fromLng], { icon: facultyIcon })
            .addTo(this.map)
            .bindPopup(`<strong>${facultyName}</strong><br><span style="font-size: 12px;">Point de départ</span>`);

        // Use dedicated OSRM instances:
        // Driving: router.project-osrm.org (Default)
        // Walking: routing.openstreetmap.de (Reliable for foot routing)
        let baseUrl = 'https://router.project-osrm.org';
        let osrmProfile = 'driving';

        if (profile === 'walking') {
            baseUrl = 'https://routing.openstreetmap.de/routed-foot';
            osrmProfile = 'foot';
        }

        // Calculate route using OSRM with alternatives=3
        const routeUrl = `${baseUrl}/route/v1/${osrmProfile}/${fromLng},${fromLat};${toLng},${toLat}?overview=full&geometries=geojson&alternatives=3`;
        console.log('Fetching route from:', routeUrl);

        try {
            const response = await fetch(routeUrl);
            console.log('Response status:', response.status);
            const data = await response.json();
            console.log('Route data received:', data);

            if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                console.log(`Found ${data.routes.length} route(s)`);

                // Render all alternative routes
                data.routes.forEach((route, index) => {
                    console.log(`Rendering route ${index + 1}/${data.routes.length}`);
                    const isMain = index === 0;
                    const coordinates = route.geometry.coordinates;
                    const latLngs = coordinates.map(coord => [coord[1], coord[0]]);

                    const routeLine = L.polyline(latLngs, {
                        color: isMain ? '#6366f1' : '#cbd5e1',
                        weight: isMain ? 8 : 5,
                        opacity: isMain ? 1 : 0.4,
                        smoothFactor: 1.5,
                        lineJoin: 'round',
                        lineCap: 'round',
                        dashArray: isMain ? null : '10, 8',
                        className: isMain ? 'main-route' : 'alternative-route'
                    }).addTo(this.map);

                    console.log(`Route ${index + 1} added to map, isMain:`, isMain);

                    if (isMain) {
                        this.mainRoute = routeLine;
                        this.map.fitBounds(routeLine.getBounds(), { padding: [50, 50] });
                        console.log('Map bounds set to main route');

                        // Display route information
                        const distance = this.formatDistance(route.distance);
                        const duration = this.formatDuration(route.duration);
                        console.log('Route info:', distance, duration, 'Profile:', profile);
                        this.displayRouteInfo(distance, duration, facultyName, profile);

                        if (this.isCompassActive) {
                            console.log('Compass is active, starting pointer animation');
                            this.startPointerAnimation(latLngs);
                        }
                    }

                    routeLine.on('click', (e) => {
                        L.DomEvent.stopPropagation(e);
                        this.selectRoute(routeLine, route, facultyName, profile);
                    });

                    routeLine.on('mouseover', () => {
                        if (this.mainRoute !== routeLine) {
                            routeLine.setStyle({
                                opacity: 0.75,
                                weight: 6,
                                color: '#94a3b8'
                            });
                        }
                    });
                    routeLine.on('mouseout', () => {
                        if (this.mainRoute !== routeLine) {
                            routeLine.setStyle({
                                opacity: 0.4,
                                weight: 5,
                                color: '#cbd5e1'
                            });
                        }
                    });
                });

                console.log('=== CALCULATE ROUTE SUCCESS ===');
                return true;
            } else {
                console.error('No routes found in response:', data);
                throw new Error('No route found');
            }
        } catch (error) {
            console.error('=== CALCULATE ROUTE ERROR ===');
            console.error('Error details:', error);
            console.error('Profile was:', profile);
            // Fallback to driving if walking fails
            if (profile === 'walking') {
                console.log('Walking failed, trying driving as fallback');
                return this.calculateRoute(fromLat, fromLng, toLat, toLng, facultyName, 'driving');
            }
            alert('Impossible de calculer l\'itinéraire. Le service est temporairement indisponible.');
            return null;
        }
    },

    /**
     * Handle route selection
     */
    selectRoute: function (routeLine, routeData, facultyName, profile) {
        // Reset all lines
        this.map.eachLayer(layer => {
            if (layer instanceof L.Polyline && !(layer instanceof L.Marker)) {
                layer.setStyle({
                    color: '#cbd5e1',
                    weight: 5,
                    opacity: 0.4,
                    dashArray: '10, 8'
                });
            }
        });

        // Highlight selected with smooth transition
        routeLine.setStyle({
            color: '#6366f1',
            weight: 8,
            opacity: 1,
            dashArray: null
        });

        this.mainRoute = routeLine;
        const distance = this.formatDistance(routeData.distance);
        const duration = this.formatDuration(routeData.duration);
        this.displayRouteInfo(distance, duration, facultyName, profile);

        if (this.isCompassActive) {
            this.startPointerAnimation(routeLine.getLatLngs());
        }
    },

    /**
     * Pointer Animation (Moving Pointer)
     */
    pointerMarker: null,
    isCompassActive: false,
    animationInterval: null,

    setCompassActive: function (active) {
        this.isCompassActive = active;
        if (!active) {
            if (this.animationInterval) clearInterval(this.animationInterval);
            if (this.pointerMarker) {
                this.map.removeLayer(this.pointerMarker);
                this.pointerMarker = null;
            }
        } else if (this.mainRoute) {
            this.startPointerAnimation(this.mainRoute.getLatLngs());
        }
    },

    startPointerAnimation: function (latLngs) {
        if (this.animationInterval) clearInterval(this.animationInterval);
        if (this.pointerMarker) {
            this.map.removeLayer(this.pointerMarker);
        }

        if (!latLngs || latLngs.length === 0) return;

        // Flatten any nested arrays from getLatLngs() if necessary
        const flatPoints = Array.isArray(latLngs[0]) && !latLngs[0].lat ? latLngs : [latLngs];
        const allPoints = [].concat(...latLngs);

        // Ensure we have L.LatLng objects
        const points = allPoints.map(p => {
            if (p instanceof L.LatLng) return p;
            if (p.lat && p.lng) return L.latLng(p.lat, p.lng);
            if (Array.isArray(p)) return L.latLng(p[0], p[1]);
            return L.latLng(p);
        }).filter(p => p && !isNaN(p.lat) && !isNaN(p.lng));

        if (points.length < 2) return;

        const pointerIcon = L.divIcon({
            className: 'moving-pointer-container',
            html: '<div class="pointer-arrow" style="font-size: 36px; transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; filter: drop-shadow(0 2px 8px rgba(99, 102, 241, 0.4));">➔</div>',
            iconSize: [48, 48],
            iconAnchor: [24, 24]
        });

        this.pointerMarker = L.marker(points[0], { icon: pointerIcon, interactive: false, zIndexOffset: 10000 }).addTo(this.map);

        let i = 0;
        let step = 0;
        const totalSteps = 3; // Interpolation steps for smoother movement
        let currentLat = points[0].lat;
        let currentLng = points[0].lng;

        this.animationInterval = setInterval(() => {
            if (!this.isCompassActive || !this.pointerMarker || !this.map) {
                clearInterval(this.animationInterval);
                return;
            }

            const curr = points[i];
            const next = points[(i + 1) % points.length];

            // Smooth interpolation between points
            const progress = step / totalSteps;
            const eased = progress < 0.5
                ? 2 * progress * progress
                : 1 - Math.pow(-2 * progress + 2, 2) / 2; // Ease in-out

            currentLat = curr.lat + (next.lat - curr.lat) * eased;
            currentLng = curr.lng + (next.lng - curr.lng) * eased;

            this.pointerMarker.setLatLng([currentLat, currentLng]);

            // Calculate bearing for rotation
            const angle = Math.atan2(next.lng - curr.lng, next.lat - curr.lat) * 180 / Math.PI;
            const adjustedRotation = angle - 90;

            const el = this.pointerMarker.getElement();
            if (el) {
                const arrow = el.querySelector('.pointer-arrow');
                if (arrow) {
                    arrow.style.transform = `rotate(${adjustedRotation}deg)`;
                }
            }

            step++;
            if (step > totalSteps) {
                step = 0;
                i = (i + 1) % points.length;
            }
        }, 80);
    },

    /**
     * Display route information card
     */
    displayRouteInfo: function (distance, duration, facultyName, profile = 'driving') {
        const existingInfo = document.getElementById('routeInfoCard');
        if (existingInfo) {
            existingInfo.remove();
        }

        let icon = '🚗';
        let modeName = 'Routier';

        if (profile === 'walking') {
            icon = '🚶';
            modeName = 'Piéton';
        }

        const infoCard = document.createElement('div');
        infoCard.id = 'routeInfoCard';
        infoCard.className = 'route-info-card fade-in';
        infoCard.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h4 style="margin: 0; font-size: 16px; font-weight: 700;">Itinéraire ${modeName}</h4>
                <button onclick="MapUtils.clearRoute()" style="background: none; border: none; cursor: pointer; font-size: 20px; color: #666;">×</button>
            </div>
            <div style="margin-bottom: 8px;">
                <span style="font-size: 14px; color: #666;">Départ:</span>
                <div style="font-weight: 600; font-size: 14px; margin-top: 2px;">${facultyName}</div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;">
                <div style="background: var(--color-surface-50); padding: 12px; border-radius: 12px; text-align: center;">
                    <div style="font-size: 24px;">📏</div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--color-brand); margin-top: 4px;">${distance}</div>
                    <div style="font-size: 12px; color: #666; margin-top: 2px;">Distance</div>
                </div>
                <div style="background: var(--color-surface-50); padding: 12px; border-radius: 12px; text-align: center;">
                    <div style="font-size: 24px;">${icon}</div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--color-brand); margin-top: 4px;">${duration}</div>
                    <div style="font-size: 12px; color: #666; margin-top: 2px;">Temps estimé</div>
                </div>
            </div>
        `;

        const mapContainer = document.getElementById('listingMap').parentElement;
        mapContainer.insertBefore(infoCard, mapContainer.firstChild);
    },

    /**
     * Clear route and markers
     */
    clearRoute: function () {
        this.clearRoutes();

        if (this.facultyMarker) {
            this.map.removeLayer(this.facultyMarker);
            this.facultyMarker = null;
        }

        const infoCard = document.getElementById('routeInfoCard');
        if (infoCard) {
            infoCard.remove();
        }

        if (this.pointerMarker) {
            this.map.removeLayer(this.pointerMarker);
            this.pointerMarker = null;
        }

        // Reset view to listing location
        if (this.listingMarker) {
            this.map.setView(this.listingMarker.getLatLng(), 13);
        }
    },

    clearRoutes: function () {
        if (!this.map) return;
        if (this.animationInterval) clearInterval(this.animationInterval);
        this.map.eachLayer(layer => {
            if (layer instanceof L.Polyline && !(layer instanceof L.Marker)) {
                this.map.removeLayer(layer);
            }
        });
        this.mainRoute = null;
    },

    /**
     * Format distance for display
     */
    formatDistance: function (meters) {
        if (meters < 1000) {
            return `${Math.round(meters)} m`;
        } else {
            return `${(meters / 1000).toFixed(1)} km`;
        }
    },

    /**
     * Format duration for display
     */
    formatDuration: function (seconds) {
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) {
            return `${minutes} min`;
        } else {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return `${hours}h ${mins}min`;
        }
    }
};

// Make MapUtils available globally
window.MapUtils = MapUtils;
