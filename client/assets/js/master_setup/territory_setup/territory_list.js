// Territory data
let territoryData = {
    countries: [],
    regions: [],
    cities: [],
    zones: [],
    areas: []
};

let filteredData = {
    countries: [],
    regions: [],
    cities: [],
    zones: [],
    areas: []
};

// DOM Elements
const searchInput = document.getElementById('searchInput');
const filterCountry = document.getElementById('filterCountry');
const filterRegion = document.getElementById('filterRegion');
const filterCity = document.getElementById('filterCity');
const filterZone = document.getElementById('filterZone');
const filterArea = document.getElementById('filterArea');
const clearFiltersBtn = document.getElementById('clearFilters');
const toast = document.getElementById('toast');
const toastMessage = document.getElementById('toastMessage');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadTerritories();
    initializeTabs();
    initializeFilters();
});

// Tab switching
function initializeTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });
}

// Initialize filters
function initializeFilters() {
    searchInput.addEventListener('input', applyFilters);
    
    // Use Select2 change event
    $(filterCountry).on('change', function() {
        updateRegionFilter();
        updateCityFilter();
        updateZoneFilter();
        updateAreaFilter();
        applyFilters();
    });
    
    $(filterRegion).on('change', function() {
        updateCityFilter();
        updateZoneFilter();
        updateAreaFilter();
        applyFilters();
    });
    
    $(filterCity).on('change', function() {
        updateZoneFilter();
        updateAreaFilter();
        applyFilters();
    });
    
    $(filterZone).on('change', function() {
        updateAreaFilter();
        applyFilters();
    });
    
    $(filterArea).on('change', function() {
        applyFilters();
    });
    
    clearFiltersBtn.addEventListener('click', clearFilters);
    
    // Initialize Select2
    $(filterCountry).select2({ placeholder: 'All Countries', allowClear: true });
    $(filterRegion).select2({ placeholder: 'All Regions', allowClear: true });
    $(filterCity).select2({ placeholder: 'All Cities', allowClear: true });
    $(filterZone).select2({ placeholder: 'All Zones', allowClear: true });
    $(filterArea).select2({ placeholder: 'All Areas', allowClear: true });
}

// Load territories from API
async function loadTerritories() {
    try {
        const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-list.php');
        const result = await response.json();
        
        console.log('Loaded data from API:', result);
        
        if (result.success && result.data) {
            territoryData = result.data;
            
            // Initialize filtered data with all data
            filteredData.countries = [...territoryData.countries];
            filteredData.regions = [...territoryData.regions];
            filteredData.cities = [...territoryData.cities];
            filteredData.zones = [...territoryData.zones];
            filteredData.areas = [...territoryData.areas];
            
            populateFilterDropdowns();
            renderAllTables();
        }
    } catch (error) {
        console.error('Failed to load territories:', error);
        showToast('Failed to load territories', 'error');
    }
}

// Populate filter dropdowns
function populateFilterDropdowns() {
    // Countries
    filterCountry.innerHTML = '<option value="">All Countries</option>';
    territoryData.countries.forEach(country => {
        const option = document.createElement('option');
        option.value = country.id;
        option.textContent = country.name;
        filterCountry.appendChild(option);
    });
    $(filterCountry).trigger('change.select2');
    
    updateRegionFilter();
    updateCityFilter();
    updateZoneFilter();
    updateAreaFilter();
}

// Update region filter based on selected country
function updateRegionFilter() {
    const selectedCountry = filterCountry.value;
    
    filterRegion.innerHTML = '<option value="">All Regions</option>';
    
    const regions = selectedCountry 
        ? territoryData.regions.filter(r => r.countryId == selectedCountry)
        : territoryData.regions;
    
    regions.forEach(region => {
        const option = document.createElement('option');
        option.value = region.id;
        option.textContent = region.name;
        filterRegion.appendChild(option);
    });
    
    $(filterRegion).val('').trigger('change.select2');
}

// Update city filter based on selected region
function updateCityFilter() {
    const selectedRegion = filterRegion.value;
    
    filterCity.innerHTML = '<option value="">All Cities</option>';
    
    const cities = selectedRegion 
        ? territoryData.cities.filter(c => c.regionId == selectedRegion)
        : territoryData.cities;
    
    cities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.id;
        option.textContent = city.name;
        filterCity.appendChild(option);
    });
    
    $(filterCity).val('').trigger('change.select2');
}

// Update zone filter based on selected city
function updateZoneFilter() {
    const selectedCity = filterCity.value;
    
    filterZone.innerHTML = '<option value="">All Zones</option>';
    
    const zones = selectedCity 
        ? territoryData.zones.filter(z => z.cityId == selectedCity)
        : territoryData.zones;
    
    zones.forEach(zone => {
        const option = document.createElement('option');
        option.value = zone.id;
        option.textContent = zone.name;
        filterZone.appendChild(option);
    });
    
    $(filterZone).val('').trigger('change.select2');
}

// Update area filter based on selected zone
function updateAreaFilter() {
    const selectedZone = filterZone.value;
    
    filterArea.innerHTML = '<option value="">All Areas</option>';
    
    const areas = selectedZone 
        ? territoryData.areas.filter(a => a.zoneId == selectedZone)
        : territoryData.areas;
    
    areas.forEach(area => {
        const option = document.createElement('option');
        option.value = area.id;
        option.textContent = area.name;
        filterArea.appendChild(option);
    });
    
    $(filterArea).val('').trigger('change.select2');
}

// Apply filters
function applyFilters() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    const selectedCountry = filterCountry.value;
    const selectedRegion = filterRegion.value;
    const selectedCity = filterCity.value;
    const selectedZone = filterZone.value;
    const selectedArea = filterArea.value;
    
    console.log('Applying filters:', { 
        searchTerm, 
        selectedCountry, 
        selectedRegion, 
        selectedCity, 
        selectedZone, 
        selectedArea 
    });
    
    // Start with all data
    let tempCountries = [...territoryData.countries];
    let tempRegions = [...territoryData.regions];
    let tempCities = [...territoryData.cities];
    let tempZones = [...territoryData.zones];
    let tempAreas = [...territoryData.areas];
    
    // Apply search filter
    if (searchTerm) {
        tempCountries = tempCountries.filter(c => c.name.toLowerCase().includes(searchTerm));
        tempRegions = tempRegions.filter(r => r.name.toLowerCase().includes(searchTerm));
        tempCities = tempCities.filter(c => c.name.toLowerCase().includes(searchTerm));
        tempZones = tempZones.filter(z => z.name.toLowerCase().includes(searchTerm));
        tempAreas = tempAreas.filter(a => a.name.toLowerCase().includes(searchTerm));
    }
    
    // Apply hierarchy filters
    if (selectedCountry) {
        tempCountries = tempCountries.filter(c => c.id == selectedCountry);
        tempRegions = tempRegions.filter(r => r.countryId == selectedCountry);
        
        // Filter cities that belong to regions in selected country
        const regionIds = tempRegions.map(r => r.id);
        tempCities = tempCities.filter(c => regionIds.includes(c.regionId));
        
        // Filter zones that belong to cities in selected country
        const cityIds = tempCities.map(c => c.id);
        tempZones = tempZones.filter(z => cityIds.includes(z.cityId));
        
        // Filter areas that belong to zones in selected country
        const zoneIds = tempZones.map(z => z.id);
        tempAreas = tempAreas.filter(a => zoneIds.includes(a.zoneId));
    }
    
    if (selectedRegion) {
        tempRegions = tempRegions.filter(r => r.id == selectedRegion);
        tempCities = tempCities.filter(c => c.regionId == selectedRegion);
        
        // Filter zones that belong to cities in selected region
        const cityIds = tempCities.map(c => c.id);
        tempZones = tempZones.filter(z => cityIds.includes(z.cityId));
        
        // Filter areas that belong to zones in selected region
        const zoneIds = tempZones.map(z => z.id);
        tempAreas = tempAreas.filter(a => zoneIds.includes(a.zoneId));
    }
    
    if (selectedCity) {
        tempCities = tempCities.filter(c => c.id == selectedCity);
        tempZones = tempZones.filter(z => z.cityId == selectedCity);
        
        // Filter areas that belong to zones in selected city
        const zoneIds = tempZones.map(z => z.id);
        tempAreas = tempAreas.filter(a => zoneIds.includes(a.zoneId));
    }
    
    if (selectedZone) {
        tempZones = tempZones.filter(z => z.id == selectedZone);
        tempAreas = tempAreas.filter(a => a.zoneId == selectedZone);
    }
    
    if (selectedArea) {
        tempAreas = tempAreas.filter(a => a.id == selectedArea);
    }
    
    // Update filtered data
    filteredData.countries = tempCountries;
    filteredData.regions = tempRegions;
    filteredData.cities = tempCities;
    filteredData.zones = tempZones;
    filteredData.areas = tempAreas;
    
    console.log('Filtered results:', {
        countries: filteredData.countries.length,
        regions: filteredData.regions.length,
        cities: filteredData.cities.length,
        zones: filteredData.zones.length,
        areas: filteredData.areas.length
    });
    
    renderAllTables();
}

// Clear all filters
function clearFilters() {
    searchInput.value = '';
    
    // Clear Select2 dropdowns
    $(filterCountry).val(null).trigger('change');
    $(filterRegion).val(null).trigger('change');
    $(filterCity).val(null).trigger('change');
    $(filterZone).val(null).trigger('change');
    $(filterArea).val(null).trigger('change');
    
    // Reset to show all data
    filteredData.countries = [...territoryData.countries];
    filteredData.regions = [...territoryData.regions];
    filteredData.cities = [...territoryData.cities];
    filteredData.zones = [...territoryData.zones];
    filteredData.areas = [...territoryData.areas];
    
    // Repopulate all dropdowns
    populateFilterDropdowns();
    
    renderAllTables();
}

// Render all tables
function renderAllTables() {
    renderCountriesTable();
    renderRegionsTable();
    renderCitiesTable();
    renderZonesTable();
    renderAreasTable();
}

// Render countries table
function renderCountriesTable() {
    const tbody = document.getElementById('countriesTable');
    const countBadge = document.getElementById('countriesCount');
    
    countBadge.textContent = filteredData.countries.length;
    
    if (filteredData.countries.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="5">No countries found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filteredData.countries.map((country, index) => {
        const regionCount = territoryData.regions.filter(r => r.countryId === country.id).length;
        const cityCount = territoryData.cities.filter(c => {
            const region = territoryData.regions.find(r => r.id === c.regionId);
            return region && region.countryId === country.id;
        }).length;
        
        return `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${country.name}</strong></td>
                <td><span class="badge">${regionCount} Regions</span></td>
                <td><span class="badge">${cityCount} Cities</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="editTerritory('country', ${country.id}, '${country.name}')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteTerritory('country', ${country.id}, '${country.name}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Render regions table
function renderRegionsTable() {
    const tbody = document.getElementById('regionsTable');
    const countBadge = document.getElementById('regionsCount');
    
    countBadge.textContent = filteredData.regions.length;
    
    if (filteredData.regions.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="5">No regions found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filteredData.regions.map((region, index) => {
        const country = territoryData.countries.find(c => c.id === region.countryId);
        const cityCount = territoryData.cities.filter(c => c.regionId === region.id).length;
        
        return `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${region.name}</strong></td>
                <td>${country ? country.name : 'N/A'}</td>
                <td><span class="badge">${cityCount} Cities</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="editTerritory('region', ${region.id}, '${region.name}')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteTerritory('region', ${region.id}, '${region.name}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Render cities table
function renderCitiesTable() {
    const tbody = document.getElementById('citiesTable');
    const countBadge = document.getElementById('citiesCount');
    
    countBadge.textContent = filteredData.cities.length;
    
    if (filteredData.cities.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="6">No cities found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filteredData.cities.map((city, index) => {
        const region = territoryData.regions.find(r => r.id === city.regionId);
        const country = region ? territoryData.countries.find(c => c.id === region.countryId) : null;
        const zoneCount = territoryData.zones.filter(z => z.cityId === city.id).length;
        
        return `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${city.name}</strong></td>
                <td>${region ? region.name : 'N/A'}</td>
                <td>${country ? country.name : 'N/A'}</td>
                <td><span class="badge">${zoneCount} Zones</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="editTerritory('city', ${city.id}, '${city.name}')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteTerritory('city', ${city.id}, '${city.name}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Render zones table
function renderZonesTable() {
    const tbody = document.getElementById('zonesTable');
    const countBadge = document.getElementById('zonesCount');
    
    countBadge.textContent = filteredData.zones.length;
    
    if (filteredData.zones.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="6">No zones found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filteredData.zones.map((zone, index) => {
        const city = territoryData.cities.find(c => c.id === zone.cityId);
        const region = city ? territoryData.regions.find(r => r.id === city.regionId) : null;
        const areaCount = territoryData.areas.filter(a => a.zoneId === zone.id).length;
        
        return `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${zone.name}</strong></td>
                <td>${city ? city.name : 'N/A'}</td>
                <td>${region ? region.name : 'N/A'}</td>
                <td><span class="badge">${areaCount} Areas</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="editTerritory('zone', ${zone.id}, '${zone.name}')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteTerritory('zone', ${zone.id}, '${zone.name}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Render areas table
function renderAreasTable() {
    const tbody = document.getElementById('areasTable');
    const countBadge = document.getElementById('areasCount');
    
    countBadge.textContent = filteredData.areas.length;
    
    if (filteredData.areas.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="5">No areas found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filteredData.areas.map((area, index) => {
        const zone = territoryData.zones.find(z => z.id === area.zoneId);
        const city = zone ? territoryData.cities.find(c => c.id === zone.cityId) : null;
        
        return `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${area.name}</strong></td>
                <td>${zone ? zone.name : 'N/A'}</td>
                <td>${city ? city.name : 'N/A'}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="editTerritory('area', ${area.id}, '${area.name}')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteTerritory('area', ${area.id}, '${area.name}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Edit territory
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
                showToast(`${type.charAt(0).toUpperCase() + type.slice(1)} updated successfully!`);
                loadTerritories();
            } else {
                showToast('Error: ' + result.message, 'error');
            }
        } catch (error) {
            showToast('Failed to update: ' + error.message, 'error');
        }
    }
}

// Delete territory
async function deleteTerritory(type, id, name) {
    if (confirm(`Are you sure you want to delete "${name}"?\n\nAll dependent territories will also be removed.`)) {
        try {
            const response = await fetch('../../../../server/api/master_setup/territory_setup/territory-delete.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id })
            });
            const result = await response.json();

            if (result.success) {
                showToast(`${type.charAt(0).toUpperCase() + type.slice(1)} deleted successfully!`);
                loadTerritories();
            } else {
                showToast('Error: ' + result.message, 'error');
            }
        } catch (error) {
            showToast('Failed to delete: ' + error.message, 'error');
        }
    }
}

// Show toast notification
function showToast(message, type = 'success') {
    toastMessage.textContent = message;
    toast.className = 'toast show';
    
    if (type === 'error') {
        toast.style.backgroundColor = '#e34f4f';
    } else {
        toast.style.backgroundColor = '#2fbf71';
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
