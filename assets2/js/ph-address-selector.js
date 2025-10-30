/**
 * Philippine Address Selector
 * Integrated with existing database reference tables (refbrgy, refcitymun, refprovince)
 * 
 * Usage:
 * <select id="region"></select>
 * <select id="province"></select>
 * <select id="city"></select>
 * <select id="barangay"></select>
 * 
 * Initialize: phAddressSelector.init();
 */

var phAddressSelector = {
    selectedRegion: null,
    selectedProvince: null,
    selectedCity: null,
    selectedBarangay: null,
    
    init: function() {
        this.loadRegions();
        this.setupEventListeners();
    },
    
    setupEventListeners: function() {
        $('#region').on('change', this.onRegionChange.bind(this));
        $('#province').on('change', this.onProvinceChange.bind(this));
        $('#city').on('change', this.onCityChange.bind(this));
        $('#barangay').on('change', this.onBarangayChange.bind(this));
    },
    
    // Load regions from database via API
    loadRegions: function() {
        let dropdown = $('#region');
        dropdown.empty().append('<option value="" selected disabled>Select Region</option>');
        dropdown.prop('selectedIndex', 0);
        
        // Check if we're in company folder or root
        var apiPath = window.location.pathname.includes('/company/') 
            ? '../common/ph-address-api.php' 
            : 'common/ph-address-api.php';
        
        // Fetch regions from API
        $.ajax({
            url: apiPath,
            method: 'GET',
            data: { type: 'regions' },
            dataType: 'json',
            success: function(data) {
                if (data.success && data.regions) {
                    $.each(data.regions, function(key, entry) {
                        dropdown.append($('<option></option>')
                            .attr('value', entry.regCode || entry.region_code)
                            .text(entry.regDesc || entry.region_name || entry.regionDesc));
                    });
                } else {
                    console.error('Failed to load regions:', data.message);
                    phAddressSelector.loadHardcodedRegions();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading regions:', error);
                // Fallback: Use hardcoded major regions if API fails
                phAddressSelector.loadHardcodedRegions();
            }
        });
    },
    
    // Fallback: Hardcoded major regions
    loadHardcodedRegions: function() {
        const regions = [
            { code: '01', name: 'Ilocos Region (Region I)' },
            { code: '02', name: 'Cagayan Valley (Region II)' },
            { code: '03', name: 'Central Luzon (Region III)' },
            { code: '04', name: 'CALABARZON (Region IV-A)' },
            { code: '05', name: 'MIMAROPA (Region IV-B)' },
            { code: '06', name: 'Bicol Region (Region V)' },
            { code: '07', name: 'Western Visayas (Region VI)' },
            { code: '08', name: 'Central Visayas (Region VII)' },
            { code: '09', name: 'Eastern Visayas (Region VIII)' },
            { code: '10', name: 'Zamboanga Peninsula (Region IX)' },
            { code: '11', name: 'Northern Mindanao (Region X)' },
            { code: '12', name: 'SOCCSKSARGEN (Region XII)' },
            { code: '13', name: 'CARAGA (Region XIII)' },
            { code: '14', name: 'National Capital Region (NCR)' },
            { code: '15', name: 'Cordillera Administrative Region (CAR)' },
            { code: '16', name: 'Autonomous Region in Muslim Mindanao (ARMM)' },
            { code: '17', name: 'Davao Region (Region XI)' }
        ];
        
        let dropdown = $('#region');
        $.each(regions, function(key, entry) {
            dropdown.append($('<option></option>')
                .attr('value', entry.code)
                .text(entry.name));
        });
    },
    
    onRegionChange: function() {
        var regionCode = $('#region').val();
        console.log('Region changed to:', regionCode);
        
        if (!regionCode) {
            $('#province').prop('disabled', true).prop('required', false).val('').trigger('change');
            $('#city').prop('disabled', true).prop('required', false).val('').trigger('change');
            $('#barangay').prop('disabled', true).prop('required', false).val('');
            return;
        }
        
        this.selectedRegion = regionCode;
        
        // Store region text
        var regionText = $('#region').find("option:selected").text();
        $('#region-text').val(regionText);
        
        // Enable province dropdown FIRST before loading
        var provinceDropdown = $('#province');
        
        // Aggressively enable the dropdown using multiple methods
        provinceDropdown.removeAttr('disabled');
        provinceDropdown.prop('disabled', false);
        provinceDropdown.prop('required', true);
        provinceDropdown.attr('disabled', false);
        provinceDropdown.css({
            'pointer-events': 'auto',
            'cursor': 'pointer',
            'opacity': '1'
        });
        
        // Clear province dropdown and add placeholder
        provinceDropdown.empty().append('<option value="" selected disabled>Select Province</option>');
        
        // Clear and reset city and barangay dropdowns
        $('#city').prop('disabled', true).prop('required', false).empty().append('<option value="" selected disabled>Select City/Municipality</option>');
        $('#barangay').prop('disabled', true).prop('required', false).empty().append('<option value="" selected disabled>Select Barangay</option>');
        
        // Verify province is enabled
        console.log('Province dropdown disabled?', provinceDropdown.prop('disabled'));
        console.log('Province dropdown has disabled attribute?', provinceDropdown.attr('disabled'));
        
        // Load provinces for selected region
        this.loadProvinces(regionCode);
        this.selectedProvince = null;
        this.selectedCity = null;
        this.selectedBarangay = null;
    },
    
    loadProvinces: function(regionCode) {
        console.log('Loading provinces for region:', regionCode);
        let dropdown = $('#province');
        
        // Don't clear again - it's already cleared in onRegionChange
        // Just ensure it has the placeholder
        if (dropdown.find('option').length === 0) {
            dropdown.append('<option value="" selected disabled>Select Province</option>');
        }
        
        // CRITICAL: Ensure dropdown is enabled BEFORE AJAX call - use multiple methods
        dropdown.removeAttr('disabled');
        dropdown.prop('disabled', false);
        dropdown.prop('required', true);
        dropdown.attr('disabled', false);
        dropdown.css({
            'pointer-events': 'auto',
            'cursor': 'pointer',
            'opacity': '1'
        });
        
        var apiPath = window.location.pathname.includes('/company/') 
            ? '../common/ph-address-api.php' 
            : 'common/ph-address-api.php';
        
        console.log('API path:', apiPath);
        console.log('Request data:', { type: 'provinces', region_code: regionCode });
        
        $.ajax({
            url: apiPath,
            method: 'GET',
            data: { type: 'provinces', region_code: regionCode },
            dataType: 'json',
            success: function(data) {
                console.log('Provinces API response:', data);
                if (data.success && data.provinces) {
                    // Clear existing options except placeholder
                    dropdown.find('option:not([value=""])').remove();
                    
                    // Sort alphabetically
                    data.provinces.sort(function(a, b) {
                        var nameA = (a.provDesc || a.province_name || '').toUpperCase();
                        var nameB = (b.provDesc || b.province_name || '').toUpperCase();
                        return nameA.localeCompare(nameB);
                    });
                    
                    // Add provinces
                    $.each(data.provinces, function(key, entry) {
                        dropdown.append($('<option></option>')
                            .attr('value', entry.provCode || entry.province_code)
                            .text(entry.provDesc || entry.province_name));
                    });
                    
                    console.log('Added', data.provinces.length, 'provinces to dropdown');
                    
                    // CRITICAL: Ensure dropdown is enabled after loading options - use multiple methods
                    dropdown.removeAttr('disabled');
                    dropdown.prop('disabled', false);
                    dropdown.prop('required', true);
                    dropdown.attr('disabled', false);
                    dropdown.css({
                        'pointer-events': 'auto',
                        'cursor': 'pointer',
                        'opacity': '1'
                    });
                    
                    // Force a reflow to ensure browser updates
                    dropdown[0].offsetHeight;
                    
                    console.log('Province dropdown enabled?', !dropdown.prop('disabled'));
                    console.log('Province dropdown options count:', dropdown.find('option').length);
                } else {
                    console.error('No provinces in response or request failed');
                    dropdown.prop('disabled', false).prop('required', true);
                    dropdown.removeAttr('disabled');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading provinces:', error);
                console.error('XHR:', xhr);
                console.error('Status:', status);
                // Ensure dropdown is still enabled even on error
                dropdown.prop('disabled', false).prop('required', true);
                dropdown.removeAttr('disabled');
            }
        });
    },
    
    onProvinceChange: function() {
        var provinceCode = $('#province').val();
        console.log('Province changed to:', provinceCode);
        console.log('Selected region:', this.selectedRegion);
        
        if (!provinceCode) {
            $('#city').prop('disabled', true).prop('required', false).val('').trigger('change');
            $('#barangay').prop('disabled', true).prop('required', false).val('');
            return;
        }
        
        this.selectedProvince = provinceCode;
        
        // Store province text
        var provinceText = $('#province').find("option:selected").text();
        $('#province-text').val(provinceText);
        
        // Enable and clear city dropdown - use aggressive enabling
        var cityDropdown = $('#city');
        cityDropdown.removeAttr('disabled');
        cityDropdown.prop('disabled', false);
        cityDropdown.prop('required', true);
        cityDropdown.attr('disabled', false);
        cityDropdown.css({
            'pointer-events': 'auto',
            'cursor': 'pointer',
            'opacity': '1'
        });
        cityDropdown.empty().append('<option value="" selected disabled>Select City/Municipality</option>');
        
        console.log('City dropdown disabled?', cityDropdown.prop('disabled'));
        
        // Clear and disable barangay dropdown
        $('#barangay').prop('disabled', true).prop('required', false).empty().append('<option value="" selected disabled>Select Barangay</option>');
        
        // Load cities for this province
        if (this.selectedRegion && provinceCode) {
            this.loadCities(this.selectedRegion, provinceCode);
        } else {
            console.error('Missing region or province code for loading cities');
        }
        this.selectedCity = null;
        this.selectedBarangay = null;
    },
    
    loadCities: function(regionCode, provinceCode) {
        console.log('Loading cities for region:', regionCode, 'province:', provinceCode);
        let dropdown = $('#city');
        
        // Don't clear again - already cleared in onProvinceChange
        if (dropdown.find('option').length === 0) {
            dropdown.append('<option value="" selected disabled>Select City/Municipality</option>');
        }
        
        // CRITICAL: Ensure dropdown is enabled BEFORE AJAX call
        dropdown.removeAttr('disabled');
        dropdown.prop('disabled', false);
        dropdown.prop('required', true);
        dropdown.attr('disabled', false);
        dropdown.css({
            'pointer-events': 'auto',
            'cursor': 'pointer',
            'opacity': '1'
        });
        
        var apiPath = window.location.pathname.includes('/company/') 
            ? '../common/ph-address-api.php' 
            : 'common/ph-address-api.php';
        
        console.log('Cities API path:', apiPath);
        console.log('Request data:', { type: 'cities', region_code: regionCode, province_code: provinceCode });
        
        $.ajax({
            url: apiPath,
            method: 'GET',
            data: { type: 'cities', region_code: regionCode, province_code: provinceCode },
            dataType: 'json',
            success: function(data) {
                console.log('Cities API response:', data);
                if (data.success && data.cities) {
                    // Clear existing options except placeholder
                    dropdown.find('option:not([value=""])').remove();
                    
                    // Sort alphabetically
                    data.cities.sort(function(a, b) {
                        var nameA = (a.citymunDesc || a.city_name || '').toUpperCase();
                        var nameB = (b.citymunDesc || b.city_name || '').toUpperCase();
                        return nameA.localeCompare(nameB);
                    });
                    
                    $.each(data.cities, function(key, entry) {
                        dropdown.append($('<option></option>')
                            .attr('value', entry.citymunCode || entry.city_code)
                            .text(entry.citymunDesc || entry.city_name));
                    });
                    
                    console.log('Added', data.cities.length, 'cities to dropdown');
                    
                    // CRITICAL: Ensure dropdown is enabled after loading options
                    dropdown.removeAttr('disabled');
                    dropdown.prop('disabled', false);
                    dropdown.prop('required', true);
                    dropdown.attr('disabled', false);
                    dropdown.css({
                        'pointer-events': 'auto',
                        'cursor': 'pointer',
                        'opacity': '1'
                    });
                    dropdown[0].offsetHeight; // Force reflow
                    
                    console.log('City dropdown enabled?', !dropdown.prop('disabled'));
                    console.log('City dropdown options count:', dropdown.find('option').length);
                } else {
                    console.error('No cities in response or request failed');
                    dropdown.removeAttr('disabled');
                    dropdown.prop('disabled', false);
                    dropdown.prop('required', true);
                    dropdown.css({
                        'pointer-events': 'auto',
                        'cursor': 'pointer',
                        'opacity': '1'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading cities:', error);
                console.error('XHR:', xhr);
                console.error('Status:', status);
                // Ensure dropdown is still enabled even on error
                dropdown.removeAttr('disabled');
                dropdown.prop('disabled', false);
                dropdown.prop('required', true);
                dropdown.css({
                    'pointer-events': 'auto',
                    'cursor': 'pointer',
                    'opacity': '1'
                });
            }
        });
    },
    
    onCityChange: function() {
        var cityCode = $('#city').val();
        console.log('City changed to:', cityCode);
        console.log('Selected region:', this.selectedRegion);
        console.log('Selected province:', this.selectedProvince);
        
        if (!cityCode) {
            $('#barangay').prop('disabled', true).prop('required', false).val('');
            return;
        }
        
        this.selectedCity = cityCode;
        
        // Store city text
        var cityText = $('#city').find("option:selected").text();
        $('#city-text').val(cityText);
        
        // Enable and clear barangay dropdown - use aggressive enabling
        var barangayDropdown = $('#barangay');
        barangayDropdown.removeAttr('disabled');
        barangayDropdown.prop('disabled', false);
        barangayDropdown.prop('required', true);
        barangayDropdown.attr('disabled', false);
        barangayDropdown.css({
            'pointer-events': 'auto',
            'cursor': 'pointer',
            'opacity': '1'
        });
        barangayDropdown.empty().append('<option value="" selected disabled>Select Barangay</option>');
        
        console.log('Barangay dropdown disabled?', barangayDropdown.prop('disabled'));
        
        // Load barangays for this city
        if (this.selectedRegion && this.selectedProvince && cityCode) {
            this.loadBarangays(this.selectedRegion, this.selectedProvince, cityCode);
        } else {
            console.error('Missing region, province, or city code for loading barangays');
        }
        this.selectedBarangay = null;
    },
    
    loadBarangays: function(regionCode, provinceCode, cityCode) {
        console.log('Loading barangays for region:', regionCode, 'province:', provinceCode, 'city:', cityCode);
        let dropdown = $('#barangay');
        
        // Don't clear again - already cleared in onCityChange
        if (dropdown.find('option').length === 0) {
            dropdown.append('<option value="" selected disabled>Select Barangay</option>');
        }
        
        // CRITICAL: Ensure dropdown is enabled BEFORE AJAX call
        dropdown.removeAttr('disabled');
        dropdown.prop('disabled', false);
        dropdown.prop('required', true);
        dropdown.attr('disabled', false);
        dropdown.css({
            'pointer-events': 'auto',
            'cursor': 'pointer',
            'opacity': '1'
        });
        
        var apiPath = window.location.pathname.includes('/company/') 
            ? '../common/ph-address-api.php' 
            : 'common/ph-address-api.php';
        
        console.log('Barangays API path:', apiPath);
        console.log('Request data:', { 
            type: 'barangays', 
            region_code: regionCode, 
            province_code: provinceCode,
            city_code: cityCode 
        });
        
        $.ajax({
            url: apiPath,
            method: 'GET',
            data: { 
                type: 'barangays', 
                region_code: regionCode, 
                province_code: provinceCode,
                city_code: cityCode 
            },
            dataType: 'json',
            success: function(data) {
                console.log('Barangays API response:', data);
                if (data.success && data.barangays) {
                    // Clear existing options except placeholder
                    dropdown.find('option:not([value=""])').remove();
                    
                    // Sort alphabetically
                    data.barangays.sort(function(a, b) {
                        var nameA = (a.brgyDesc || a.barangay_name || '').toUpperCase();
                        var nameB = (b.brgyDesc || b.barangay_name || '').toUpperCase();
                        return nameA.localeCompare(nameB);
                    });
                    
                    $.each(data.barangays, function(key, entry) {
                        dropdown.append($('<option></option>')
                            .attr('value', entry.brgyCode || entry.barangay_code)
                            .text(entry.brgyDesc || entry.barangay_name));
                    });
                    
                    console.log('Added', data.barangays.length, 'barangays to dropdown');
                    
                    // CRITICAL: Ensure dropdown is enabled after loading options
                    dropdown.removeAttr('disabled');
                    dropdown.prop('disabled', false);
                    dropdown.prop('required', true);
                    dropdown.attr('disabled', false);
                    dropdown.css({
                        'pointer-events': 'auto',
                        'cursor': 'pointer',
                        'opacity': '1'
                    });
                    dropdown[0].offsetHeight; // Force reflow
                    
                    console.log('Barangay dropdown enabled?', !dropdown.prop('disabled'));
                    console.log('Barangay dropdown options count:', dropdown.find('option').length);
                } else {
                    console.error('No barangays in response or request failed');
                    dropdown.removeAttr('disabled');
                    dropdown.prop('disabled', false);
                    dropdown.prop('required', true);
                    dropdown.css({
                        'pointer-events': 'auto',
                        'cursor': 'pointer',
                        'opacity': '1'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading barangays:', error);
                console.error('XHR:', xhr);
                console.error('Status:', status);
                // Ensure dropdown is still enabled even on error
                dropdown.removeAttr('disabled');
                dropdown.prop('disabled', false);
                dropdown.prop('required', true);
                dropdown.css({
                    'pointer-events': 'auto',
                    'cursor': 'pointer',
                    'opacity': '1'
                });
            }
        });
    },
    
    onBarangayChange: function() {
        var barangayCode = $(this).val();
        if (!barangayCode) return;
        
        this.selectedBarangay = barangayCode;
        
        // Store barangay text
        var barangayText = $(this).find("option:selected").text();
        $('#barangay-text').val(barangayText);
        
        // Update full address display
        this.updateFullAddress();
    },
    
    updateFullAddress: function() {
        var addressParts = [];
        
        var street = $('#address-line').val();
        if (street) addressParts.push(street.trim());
        
        var barangay = $('#barangay-text').val();
        if (barangay) addressParts.push(barangay);
        
        var city = $('#city-text').val();
        if (city) addressParts.push(city);
        
        var province = $('#province-text').val();
        if (province) addressParts.push(province);
        
        var region = $('#region-text').val();
        if (region) addressParts.push(region);
        
        var fullAddress = addressParts.join(', ');
        $('#full-address-display').text(fullAddress);
        $('#full-address-hidden').val(fullAddress);
    }
};

// Initialize on document ready
$(document).ready(function() {
    phAddressSelector.init();
    
    // Update address preview when street address changes
    $('#address-line').on('input', function() {
        phAddressSelector.updateFullAddress();
    });
});
