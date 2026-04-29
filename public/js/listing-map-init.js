// Map initialization function for listing detail page
async function initializeListingMap() {
    // Wait for DOM to be fully rendered
    setTimeout(async () => {
        try {
            if (!currentListing || !currentListing.latitude || !currentListing.longitude) {
                console.log('Listing does not have coordinates, skipping map initialization');
                const mapSection = document.getElementById('mapSection');
                if (mapSection) mapSection.style.display = 'none';
                return;
            }

            // Initialize map
            MapUtils.initListingMap('listingMap', currentListing.latitude, currentListing.longitude, {
                title: currentListing.title,
                price: formatCurrency(currentListing.price),
                address: currentListing.address
            });

            // Load faculties and populate dropdown
            const faculties = await MapUtils.loadFaculties();
            const facultySelector = document.getElementById('facultySelector');
            const getDirectionsBtn = document.getElementById('getDirectionsBtn');

            // Store current route data globally for mode switching
            window.currentRouteData = null;

            if (faculties && faculties.length > 0) {
                // Group faculties by governorate
                const grouped = {};
                faculties.forEach(faculty => {
                    if (!grouped[faculty.governorate]) {
                        grouped[faculty.governorate] = [];
                    }
                    grouped[faculty.governorate].push(faculty);
                });

                // Add faculties to dropdown grouped by governorate
                Object.keys(grouped).sort().forEach(governorate => {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = governorate;

                    grouped[governorate].forEach(faculty => {
                        const option = document.createElement('option');
                        option.value = faculty.id;
                        option.textContent = faculty.name;
                        option.dataset.lat = faculty.latitude;
                        option.dataset.lng = faculty.longitude;
                        option.dataset.name = faculty.name;
                        optgroup.appendChild(option);
                    });

                    facultySelector.appendChild(optgroup);
                });

                // Enable button when faculty is selected
                facultySelector.addEventListener('change', () => {
                    getDirectionsBtn.disabled = !facultySelector.value;
                });

                // Handle get directions button click
                getDirectionsBtn.addEventListener('click', async () => {
                    const selectedOption = facultySelector.options[facultySelector.selectedIndex];
                    if (selectedOption && selectedOption.dataset.lat) {
                        const facultyLat = parseFloat(selectedOption.dataset.lat);
                        const facultyLng = parseFloat(selectedOption.dataset.lng);
                        const facultyName = selectedOption.dataset.name;

                        // Store route data for recalculation
                        window.currentRouteData = {
                            fromLat: facultyLat,
                            fromLng: facultyLng,
                            toLat: currentListing.latitude,
                            toLng: currentListing.longitude,
                            facultyName: facultyName
                        };

                        console.log('Calculating route with mode:', window.travelMode || 'driving');
                        try {
                            await MapUtils.calculateRoute(
                                facultyLat,
                                facultyLng,
                                currentListing.latitude,
                                currentListing.longitude,
                                facultyName,
                                window.travelMode || 'driving'
                            );
                            console.log('Route calculated successfully');
                        } catch (error) {
                            console.error('Error calculating route:', error);
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Error initializing map:', error);
        }
    }, 500);
}
