// Territory data structure
const territoryData = {
    countries: [],
    regions: [],
    cities: [],
    zones: [],
    areas: []
};

// DOM Elements
const countryNameInput = document.getElementById('countryName');
const regionNameInput = document.getElementById('regionName');
const cityNameInput = document.getElementById('cityName');
const zoneNameInput = document.getElementById('zoneName');
const areaNameInput = document.getElementById('areaName');

const selectCountry = document.getElementById('selectCountry');
const selectRegion = document.getElementById('selectRegion');
const selectCity = document.getElementById('selectCity');
const selectZone = document.getElementById('selectZone');

const addCountryBtn = document.getElementById('addCountryBtn');
const addRegionBtn = document.getElementById('addRegionBtn');
const addCityBtn = document.getElementById('addCityBtn');
const addZoneBtn = document.getElementById('addZoneBtn');
const addAreaBtn = document.getElementById('addAreaBtn');

const resetBtn = document.getElementById('resetBtn');
const saveDraftBtn = document.getElementById('saveDraftBtn');
const submitBtn = document.getElementById('submitBtn');
const helpBtn = document.getElementById('helpBtn');

const hierarchyContainer = document.getElementById('hierarchyContainer');
const emptyHierarchy = document.getElementById('emptyHierarchy');
const successMessage = document.getElementById('successMessage');

// Generate unique ID
function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

// Show success message
function showSuccess(message = "Territory added successfully!") {
    successMessage.querySelector('strong').textContent = message;
    successMessage.classList.add('show');

    setTimeout(() => {
        successMessage.classList.remove('show');
    }, 3000);
}

// Update dropdown options
function updateDropdowns() {
    // Update country dropdown
    selectCountry.innerHTML = '<option value="">-- Select a country --</option>';
    territoryData.countries.forEach(country => {
        const option = document.createElement('option');
        option.value = country.id;
        option.textContent = country.name;
        selectCountry.appendChild(option);
    });
    $(selectCountry).select2({ placeholder: '-- Select a country --', allowClear: true });

    // Update region dropdown
    selectRegion.innerHTML = '<option value="">-- Select a region --</option>';
    territoryData.regions.forEach(region => {
        const country = territoryData.countries.find(c => c.id === region.countryId);
        const option = document.createElement('option');
        option.value = region.id;
        option.textContent = `${region.name} (${country ? country.name : 'Unknown'})`;
        selectRegion.appendChild(option);
    });
    $(selectRegion).select2({ placeholder: '-- Select a region --', allowClear: true });

    // Update city dropdown
    selectCity.innerHTML = '<option value="">-- Select a city --</option>';
    territoryData.cities.forEach(city => {
        const region = territoryData.regions.find(r => r.id === city.regionId);
        const option = document.createElement('option');
        option.value = city.id;
        option.textContent = `${city.name} (${region ? region.name : 'Unknown'})`;
        selectCity.appendChild(option);
    });
    $(selectCity).select2({ placeholder: '-- Select a city --', allowClear: true });

    // Update zone dropdown
    selectZone.innerHTML = '<option value="">-- Select a zone --</option>';
    territoryData.zones.forEach(zone => {
        const city = territoryData.cities.find(c => c.id === zone.cityId);
        const option = document.createElement('option');
        option.value = zone.id;
        option.textContent = `${zone.name} (${city ? city.name : 'Unknown'})`;
        selectZone.appendChild(option);
    });
    $(selectZone).select2({ placeholder: '-- Select a zone --', allowClear: true });
}

// Render hierarchy visualization
function renderHierarchy() {
    if (territoryData.countries.length === 0) {
        emptyHierarchy.style.display = 'block';
        return;
    }

    emptyHierarchy.style.display = 'none';
    hierarchyContainer.innerHTML = '';

    // Countries
    territoryData.countries.forEach(country => {
        const countryElement = document.createElement('div');
        countryElement.className = 'hierarchy-level';

        countryElement.innerHTML = `
                    <div class="hierarchy-level-label">
                        <i class="fas fa-globe"></i> Country
                    </div>
                    <div class="hierarchy-items">
                        <div class="hierarchy-item">
                            <span class="item-name" data-type="country" data-id="${country.id}">${country.name}</span>
                            <div class="item-actions">
                                <button class="edit-btn" data-type="country" data-id="${country.id}" data-name="${country.name}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="remove-btn" data-type="country" data-id="${country.id}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;

        hierarchyContainer.appendChild(countryElement);

        // Regions under this country
        const countryRegions = territoryData.regions.filter(region => region.countryId === country.id);
        if (countryRegions.length > 0) {
            const regionElement = document.createElement('div');
            regionElement.className = 'hierarchy-level';

            let regionItems = '';
            countryRegions.forEach(region => {
                regionItems += `
                            <div class="hierarchy-item">
                                <span class="item-name" data-type="region" data-id="${region.id}">${region.name}</span>
                                <div class="item-actions">
                                    <button class="edit-btn" data-type="region" data-id="${region.id}" data-name="${region.name}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="remove-btn" data-type="region" data-id="${region.id}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        `;
            });

            regionElement.innerHTML = `
                        <div class="hierarchy-level-label">
                            <i class="fas fa-map"></i> Regions
                        </div>
                        <div class="hierarchy-items">
                            ${regionItems}
                        </div>
                    `;

            hierarchyContainer.appendChild(regionElement);

            // Cities under each region
            countryRegions.forEach(region => {
                const regionCities = territoryData.cities.filter(city => city.regionId === region.id);
                if (regionCities.length > 0) {
                    const cityElement = document.createElement('div');
                    cityElement.className = 'hierarchy-level';

                    let cityItems = '';
                    regionCities.forEach(city => {
                        cityItems += `
                                    <div class="hierarchy-item">
                                        <span class="item-name" data-type="city" data-id="${city.id}">${city.name}</span>
                                        <div class="item-actions">
                                            <button class="edit-btn" data-type="city" data-id="${city.id}" data-name="${city.name}">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="remove-btn" data-type="city" data-id="${city.id}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;
                    });

                    cityElement.innerHTML = `
                                <div class="hierarchy-level-label">
                                    <i class="fas fa-city"></i> Cities in ${region.name}
                                </div>
                                <div class="hierarchy-items">
                                    ${cityItems}
                                </div>
                            `;

                    hierarchyContainer.appendChild(cityElement);

                    // Zones under each city
                    regionCities.forEach(city => {
                        const cityZones = territoryData.zones.filter(zone => zone.cityId === city.id);
                        if (cityZones.length > 0) {
                            const zoneElement = document.createElement('div');
                            zoneElement.className = 'hierarchy-level';

                            let zoneItems = '';
                            cityZones.forEach(zone => {
                                zoneItems += `
                                            <div class="hierarchy-item">
                                                <span class="item-name" data-type="zone" data-id="${zone.id}">${zone.name}</span>
                                                <div class="item-actions">
                                                    <button class="edit-btn" data-type="zone" data-id="${zone.id}" data-name="${zone.name}">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="remove-btn" data-type="zone" data-id="${zone.id}">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        `;
                            });

                            zoneElement.innerHTML = `
                                        <div class="hierarchy-level-label">
                                            <i class="fas fa-th-large"></i> Zones in ${city.name}
                                        </div>
                                        <div class="hierarchy-items">
                                            ${zoneItems}
                                        </div>
                                    `;

                            hierarchyContainer.appendChild(zoneElement);

                            // Areas under each zone
                            cityZones.forEach(zone => {
                                const zoneAreas = territoryData.areas.filter(area => area.zoneId === zone.id);
                                if (zoneAreas.length > 0) {
                                    const areaElement = document.createElement('div');
                                    areaElement.className = 'hierarchy-level';

                                    let areaItems = '';
                                    zoneAreas.forEach(area => {
                                        areaItems += `
                                                    <div class="hierarchy-item">
                                                        <span class="item-name" data-type="area" data-id="${area.id}">${area.name}</span>
                                                        <div class="item-actions">
                                                            <button class="edit-btn" data-type="area" data-id="${area.id}" data-name="${area.name}">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="remove-btn" data-type="area" data-id="${area.id}">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                `;
                                    });

                                    areaElement.innerHTML = `
                                                <div class="hierarchy-level-label">
                                                    <i class="fas fa-map-marker-alt"></i> Areas in ${zone.name}
                                                </div>
                                                <div class="hierarchy-items">
                                                    ${areaItems}
                                                </div>
                                            `;

                                    hierarchyContainer.appendChild(areaElement);
                                }
                            });
                        }
                    });
                }
            });
        }
    });

    // Add event listeners to remove buttons
    document.querySelectorAll('.remove-btn').forEach(button => {
        button.addEventListener('click', function () {
            const type = this.getAttribute('data-type');
            const id = this.getAttribute('data-id');
            removeTerritory(type, id);
        });
    });

    // Add event listeners to edit buttons
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const type = this.getAttribute('data-type');
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            editTerritory(type, id, name);
        });
    });
}

// Remove territory item
async function removeTerritory(type, id) {
    if (confirm(`Are you sure you want to remove this ${type}? All dependent territories will also be removed.`)) {
        try {
            const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-delete.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id })
            });
            const result = await response.json();

            if (result.success) {
                showSuccess('Territory removed successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Failed to remove territory: ' + error.message);
        }
    }
}

// Edit territory item
async function editTerritory(type, id, currentName) {
    const newName = prompt(`Edit ${type} name:`, currentName);
    
    if (newName && newName.trim() !== '' && newName !== currentName) {
        try {
            const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-edit.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id, name: newName.trim() })
            });
            const result = await response.json();

            if (result.success) {
                showSuccess(`${type} updated successfully!`);
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Failed to update territory: ' + error.message);
        }
    }
}

// Add country
async function addCountry() {
    const name = countryNameInput.value.trim();

    if (!name) {
        alert('Please enter a country name');
        countryNameInput.focus();
        return;
    }

    try {
        const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'country', name })
        });
        const result = await response.json();

        if (result.success) {
            showSuccess(`Country "${name}" added successfully!`);
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Failed to add country: ' + error.message);
    }
}

// Add region
async function addRegion() {
    const name = regionNameInput.value.trim();
    const countryId = selectCountry.value;

    if (!name) {
        alert('Please enter a region name');
        regionNameInput.focus();
        return;
    }

    if (!countryId) {
        alert('Please select a country');
        selectCountry.focus();
        return;
    }

    try {
        const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'region', name, country_id: countryId })
        });
        const result = await response.json();

        if (result.success) {
            showSuccess(`Region "${name}" added successfully!`);
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Failed to add region: ' + error.message);
    }
}

// Add city
async function addCity() {
    const name = cityNameInput.value.trim();
    const regionId = selectRegion.value;

    if (!name) {
        alert('Please enter a city name');
        cityNameInput.focus();
        return;
    }

    if (!regionId) {
        alert('Please select a region');
        selectRegion.focus();
        return;
    }

    try {
        const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'city', name, region_id: regionId })
        });
        const result = await response.json();

        if (result.success) {
            showSuccess(`City "${name}" added successfully!`);
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Failed to add city: ' + error.message);
    }
}

// Add zone
async function addZone() {
    const name = zoneNameInput.value.trim();
    const cityId = selectCity.value;

    if (!name) {
        alert('Please enter a zone name');
        zoneNameInput.focus();
        return;
    }

    if (!cityId) {
        alert('Please select a city');
        selectCity.focus();
        return;
    }

    try {
        const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'zone', name, city_id: cityId })
        });
        const result = await response.json();

        if (result.success) {
            showSuccess(`Zone "${name}" added successfully!`);
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Failed to add zone: ' + error.message);
    }
}

// Add area
async function addArea() {
    const name = areaNameInput.value.trim();
    const zoneId = selectZone.value;

    if (!name) {
        alert('Please enter an area name');
        areaNameInput.focus();
        return;
    }

    if (!zoneId) {
        alert('Please select a zone');
        selectZone.focus();
        return;
    }

    try {
        const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'area', name, city_zone_id: zoneId })
        });
        const result = await response.json();

        if (result.success) {
            showSuccess(`Area "${name}" added successfully!`);
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Failed to add area: ' + error.message);
    }
}

// Reset form
function resetForm() {
    if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        // Clear inputs
        countryNameInput.value = '';
        regionNameInput.value = '';
        cityNameInput.value = '';
        zoneNameInput.value = '';
        areaNameInput.value = '';

        // Clear territory data
        territoryData.countries = [];
        territoryData.regions = [];
        territoryData.cities = [];
        territoryData.zones = [];
        territoryData.areas = [];

        // Update UI
        updateDropdowns();
        renderHierarchy();
    }
}

// Save draft
function saveDraft() {
    if (territoryData.countries.length === 0) {
        alert('Please add at least one country before saving.');
        return;
    }

    // In a real app, this would send data to a server
    localStorage.setItem('territoryDraft', JSON.stringify(territoryData));
    showSuccess('Draft saved successfully!');
}

// Submit form
function submitForm() {
    if (territoryData.countries.length === 0) {
        alert('Please add at least one country before submitting.');
        return;
    }

    // In a real app, this would send data to a server
    const territorySummary = {
        countries: territoryData.countries.length,
        regions: territoryData.regions.length,
        cities: territoryData.cities.length,
        zones: territoryData.zones.length,
        areas: territoryData.areas.length
    };

    alert(`Territory setup completed!\n\nSummary:\n- Countries: ${territorySummary.countries}\n- Regions: ${territorySummary.regions}\n- Cities: ${territorySummary.cities}\n- Zones: ${territorySummary.zones}\n- Areas: ${territorySummary.areas}\n\nThis data would now be saved to your ERP system.`);

    // Reset after successful submission
    resetForm();
}

// Show help
function showHelp() {
    alert('Territory Setup Help:\n\n1. Start by adding a Country with a name and 2-letter code.\n2. Add Regions under the selected Country.\n3. Add Cities under the selected Region.\n4. Add Zones under the selected City.\n5. Add Areas under the selected Zone.\n6. The hierarchy visualization will update automatically.\n7. Use the "Complete Setup" button when finished.');
}

// Event Listeners
addCountryBtn.addEventListener('click', addCountry);
addRegionBtn.addEventListener('click', addRegion);
addCityBtn.addEventListener('click', addCity);
addZoneBtn.addEventListener('click', addZone);
addAreaBtn.addEventListener('click', addArea);
resetBtn.addEventListener('click', resetForm);
saveDraftBtn.addEventListener('click', saveDraft);
submitBtn.addEventListener('click', submitForm);
helpBtn.addEventListener('click', showHelp);

// Allow Enter key to submit forms
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
        e.preventDefault();

        // Determine which button to trigger based on which input is focused
        if (e.target === countryNameInput) {
            addCountry();
        } else if (e.target === regionNameInput) {
            addRegion();
        } else if (e.target === cityNameInput) {
            addCity();
        } else if (e.target === zoneNameInput) {
            addZone();
        } else if (e.target === areaNameInput) {
            addArea();
        }
    }
});

// Load territories from database on page load
window.addEventListener('load', async function () {
    try {
        const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-list.php');
        const result = await response.json();
        
        if (result.success && result.data) {
            Object.assign(territoryData, result.data);
            updateDropdowns();
            renderHierarchy();
        }
    } catch (error) {
        console.error('Failed to load territories:', error);
    }
});

