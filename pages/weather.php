<?php

if (!isLoggedIn()) {
    redirect('login');
}
include __DIR__ . '/../layouts/header.php';

$user = getCurrentUser();
?>

<section class="hero">
    <h1><?php echo __('weather_alerts_title'); ?></h1>
    <p><?php echo __('realtime_weather_info'); ?></p>
</section>

<div class="weather-grid">
    <div class="weather-card-wrapper">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="material-icons" style="vertical-align: middle;">wb_cloudy</span>
                    <?php echo __('current_weather'); ?>
                </h3>
            </div>

            <div id="weatherData" class="weather-data-display">
                <div class="weather-icon-large">
                    <span class="material-icons">wb_sunny</span>
                </div>
                <h2 class="weather-temp">28°C</h2>
                <p class="weather-condition">Partly Cloudy</p>
                
                <div class="weather-details">
                    <p><span class="material-icons">water_drop</span> <strong><?php echo __('humidity'); ?>:</strong> 65%</p>
                    <p><span class="material-icons">air</span> <strong><?php echo __('wind_speed'); ?>:</strong> 12 km/h</p>
                    <p><span class="material-icons">grain</span> <strong><?php echo __('rainfall'); ?>:</strong> 2.5 mm</p>
                    <p><span class="material-icons">speed</span> <strong><?php echo __('pressure'); ?>:</strong> 1013 mb</p>
                </div>
            </div>
        </div>
    </div>

    <div class="weather-card-wrapper">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="material-icons" style="vertical-align: middle;">location_on</span>
                    <?php echo __('location'); ?>
                </h3>
            </div>

            <div class="form-group">
                <label for="region"><?php echo __('your_region'); ?></label>
                <select id="region" name="region">
                    <option>Dhaka</option>
                    <option>Chittagong</option>
                    <option>Khulna</option>
                    <option>Rangpur</option>
                    <option>Sylhet</option>
                    <option>Barisal</option>
                </select>
            </div>

            <button class="btn btn-detect-location btn-block">
                <span class="material-icons" style="vertical-align: middle; font-size: 18px;">my_location</span>
                <?php echo __('auto_detect_location'); ?>
            </button>

            <div id="locationInfo" class="location-info-hidden">
                <p><strong>Latitude:</strong> <span id="locationLat"></span></p>
                <p><strong>Longitude:</strong> <span id="locationLng"></span></p>
            </div>
        </div>
    </div>
</div>

<h2 class="mt-4"><?php echo __('day_forecast'); ?></h2>
<div class="table-responsive">
<table class="weather-forecast-table">
    <thead>
        <tr>
            <th><?php echo __('day'); ?></th>
            <th><?php echo __('condition'); ?></th>
            <th><?php echo __('max_temp'); ?></th>
            <th><?php echo __('min_temp'); ?></th>
            <th><?php echo __('humidity'); ?></th>
            <th><?php echo __('rainfall'); ?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?php echo __('today'); ?></td>
            <td>Partly Cloudy</td>
            <td>28°C</td>
            <td>22°C</td>
            <td>65%</td>
            <td>2.5 mm</td>
        </tr>
        <tr>
            <td>Tomorrow</td>
            <td>Rainy</td>
            <td>26°C</td>
            <td>21°C</td>
            <td>75%</td>
            <td>8.0 mm</td>
        </tr>
        <tr>
            <td>Day 3</td>
            <td>Cloudy</td>
            <td>27°C</td>
            <td>20°C</td>
            <td>70%</td>
            <td>4.0 mm</td>
        </tr>
        <tr>
            <td>Day 4</td>
            <td>Sunny</td>
            <td>29°C</td>
            <td>23°C</td>
            <td>55%</td>
            <td>0.0 mm</td>
        </tr>
        <tr>
            <td>Day 5</td>
            <td>Sunny</td>
            <td>30°C</td>
            <td>24°C</td>
            <td>50%</td>
            <td>0.0 mm</td>
        </tr>
        <tr>
            <td>Day 6</td>
            <td>Partly Cloudy</td>
            <td>29°C</td>
            <td>23°C</td>
            <td>60%</td>
            <td>1.5 mm</td>
        </tr>
        <tr>
            <td>Day 7</td>
            <td>Rainy</td>
            <td>27°C</td>
            <td>22°C</td>
            <td>72%</td>
            <td>6.0 mm</td>
        </tr>
    </tbody>
</table>
</div>

<h2 class="mt-4">
    <span class="material-icons" style="vertical-align: middle; color: #ff9800;">warning</span>
    Active Alerts
</h2>
<div class="alerts-container">
    <div class="notice notice-warning">
        <div class="alert-content">
            <div class="alert-header">
                <span class="material-icons">water</span>
                <strong>Flood Advisory</strong>
            </div>
            <p>Heavy rainfall expected in the next 2 days. Water levels may rise in low-lying areas.</p>
            <small>Valid until: Dec 16, 2025</small>
        </div>
    </div>

    <div class="notice notice-info">
        <div class="alert-content">
            <div class="alert-header">
                <span class="material-icons">air</span>
                <strong>Wind Advisory</strong>
            </div>
            <p>Strong winds expected from the south at 40-50 km/h. Secure loose farm structures.</p>
            <small>Valid until: Dec 15, 2025</small>
        </div>
    </div>

    <div class="notice notice-success">
        <div class="alert-content">
            <div class="alert-header">
                <span class="material-icons">check_circle</span>
                <strong>Good Farming Weather</strong>
            </div>
            <p>Conditions are favorable for planting and crop management today.</p>
            <small>Updated: Today 09:30 AM</small>
        </div>
    </div>
</div>

<h2 class="mt-4">
    <span class="material-icons" style="vertical-align: middle; color: var(--secondary);">agriculture</span>
    Farming Recommendations
</h2>
<div class="recommendations-grid">
    <div class="card recommendation-card recommended">
        <h4>
            <span class="material-icons">check_circle</span>
            Recommended Today
        </h4>
        <ul class="recommendation-list">
            <li><span class="material-icons">done</span> Spraying pesticides (low wind)</li>
            <li><span class="material-icons">done</span> Light irrigation</li>
            <li><span class="material-icons">done</span> Harvesting (dry conditions expected)</li>
        </ul>
    </div>

    <div class="card recommendation-card not-recommended">
        <h4>
            <span class="material-icons">pause_circle</span>
            Not Recommended
        </h4>
        <ul class="recommendation-list">
            <li><span class="material-icons">close</span> Heavy watering (rain expected)</li>
            <li><span class="material-icons">close</span> Fertilizer application</li>
            <li><span class="material-icons">close</span> Soil preparation</li>
        </ul>
    </div>

    <div class="card recommendation-card precaution">
        <h4>
            <span class="material-icons">warning</span>
            Take Precautions
        </h4>
        <ul class="recommendation-list">
            <li><span class="material-icons">shield</span> Secure farm structures</li>
            <li><span class="material-icons">shield</span> Monitor drainage systems</li>
            <li><span class="material-icons">shield</span> Prepare for possible flooding</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
