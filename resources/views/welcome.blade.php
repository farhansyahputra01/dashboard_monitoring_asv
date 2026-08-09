<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ASV 2026 Telemetry Dashboard</title>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700|outfit:500,600,700,800" rel="stylesheet" />
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f111a;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,0.2) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,0.2) 0, transparent 50%);
            background-attachment: fixed;
            color: #e2e8f0;
        }
        
        .font-display {
            font-family: 'Outfit', sans-serif;
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .glass-panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .value-text {
            background: linear-gradient(to right, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 20px rgba(56, 189, 248, 0.2);
        }

        .indicator {
            position: relative;
            display: inline-flex;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .indicator::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 10px #10b981;
            animation: pulse-green 2s infinite;
        }

        .indicator.disconnected::before {
            background-color: #ef4444;
            box-shadow: 0 0 10px #ef4444;
            animation: pulse-red 2s infinite;
        }
        
        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        
        .compass-ring {
            transition: transform 0.5s ease-out;
        }
    </style>
</head>
<body class="min-h-screen antialiased selection:bg-indigo-500/30">
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <header class="flex flex-col sm:flex-row items-center justify-between mb-10 gap-4">
            <div>
                <h1 class="text-3xl sm:text-4xl font-display font-bold text-white tracking-tight">ASV Telemetry</h1>
                <p class="text-slate-400 mt-1">Real-time monitoring dashboard for Autonomous Surface Vehicle</p>
            </div>
            
            <div class="glass-panel rounded-full px-4 py-2 flex items-center gap-4">
                <div id="connection-status" class="indicator disconnected">
                    <span id="status-text" class="text-slate-300">Connecting...</span>
                </div>
            </div>
        </header>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Battery Card -->
            <div class="glass-panel rounded-2xl p-6 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-green-500/10 rounded-full blur-2xl group-hover:bg-green-500/20 transition-all"></div>
                <h3 class="text-slate-400 font-medium text-sm mb-4 uppercase tracking-wider">Power System</h3>
                <div class="flex items-end gap-2 mb-1">
                    <span id="val-battery-percent" class="font-display font-bold text-5xl value-text">--</span>
                    <span class="text-slate-500 font-medium text-xl mb-1">%</span>
                </div>
                <div class="flex justify-between items-center mt-4 text-sm">
                    <span class="text-slate-400">Voltage</span>
                    <span id="val-voltage" class="text-slate-200 font-medium">-- V</span>
                </div>
                <div class="flex justify-between items-center mt-2 text-sm">
                    <span class="text-slate-400">Current</span>
                    <span id="val-current" class="text-slate-200 font-medium">-- A</span>
                </div>
            </div>

            <!-- GPS/Location Card -->
            <div class="glass-panel rounded-2xl p-6 lg:col-span-2 relative overflow-hidden group">
                <div class="absolute -right-10 -top-10 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all"></div>
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-slate-400 font-medium text-sm uppercase tracking-wider">Navigation</h3>
                    <div class="flex items-center gap-1 bg-slate-800/50 rounded-md px-2 py-1 text-xs">
                        <svg class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path></svg>
                        <span id="val-satellites" class="text-slate-300 font-medium">0 Sats</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-slate-500 text-xs mb-1">Latitude</div>
                        <div id="val-lat" class="font-display font-bold text-2xl text-slate-100">--</div>
                    </div>
                    <div>
                        <div class="text-slate-500 text-xs mb-1">Longitude</div>
                        <div id="val-lng" class="font-display font-bold text-2xl text-slate-100">--</div>
                    </div>
                    <div>
                        <div class="text-slate-500 text-xs mb-1">Speed</div>
                        <div class="flex items-end gap-1">
                            <span id="val-speed" class="font-display font-bold text-2xl text-slate-100">--</span>
                            <span class="text-slate-500 text-sm mb-0.5">km/h</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-500 text-xs mb-1">Altitude</div>
                        <div class="flex items-end gap-1">
                            <span id="val-alt" class="font-display font-bold text-2xl text-slate-100">--</span>
                            <span class="text-slate-500 text-sm mb-0.5">m</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compass Card -->
            <div class="glass-panel rounded-2xl p-6 relative overflow-hidden group flex flex-col items-center justify-center">
                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl group-hover:bg-indigo-500/20 transition-all"></div>
                <h3 class="text-slate-400 font-medium text-sm absolute top-6 left-6 uppercase tracking-wider">Heading</h3>
                
                <div class="relative w-32 h-32 mt-4 flex items-center justify-center">
                    <!-- Compass rose -->
                    <div id="compass-ring" class="compass-ring absolute inset-0 rounded-full border-2 border-slate-700 border-dashed">
                        <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-[10px] font-bold text-red-400">N</div>
                        <div class="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 text-[10px] font-bold text-slate-500">S</div>
                        <div class="absolute right-0 top-1/2 transform translate-x-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-500">E</div>
                        <div class="absolute left-0 top-1/2 transform -translate-x-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-500">W</div>
                    </div>
                    
                    <!-- Arrow -->
                    <svg class="w-12 h-12 text-indigo-400 drop-shadow-[0_0_8px_rgba(129,140,248,0.5)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L20 21L12 17L4 21L12 2Z"></path>
                    </svg>
                </div>
                
                <div class="mt-4 flex items-end gap-1">
                    <span id="val-heading" class="font-display font-bold text-3xl value-text">--</span>
                    <span class="text-slate-500 font-medium text-lg mb-1">°</span>
                </div>
            </div>

            <!-- Environment Card -->
            <div class="glass-panel rounded-2xl p-6 lg:col-span-4 relative overflow-hidden group">
                <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-purple-500/10 rounded-full blur-3xl group-hover:bg-purple-500/20 transition-all"></div>
                <h3 class="text-slate-400 font-medium text-sm mb-6 uppercase tracking-wider">Internal Environment (DHT11)</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                    <div class="flex items-center gap-6 p-4 rounded-xl bg-slate-800/30 border border-slate-700/50">
                        <div class="w-14 h-14 rounded-full bg-orange-500/10 flex items-center justify-center text-orange-400">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                        <div>
                            <div class="text-slate-400 text-sm mb-1">Temperature</div>
                            <div class="flex items-end gap-1">
                                <span id="val-temp" class="font-display font-bold text-4xl text-slate-100">--</span>
                                <span class="text-slate-500 font-medium text-xl mb-1">°C</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-6 p-4 rounded-xl bg-slate-800/30 border border-slate-700/50">
                        <div class="w-14 h-14 rounded-full bg-cyan-500/10 flex items-center justify-center text-cyan-400">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                        </div>
                        <div>
                            <div class="text-slate-400 text-sm mb-1">Humidity</div>
                            <div class="flex items-end gap-1">
                                <span id="val-humidity" class="font-display font-bold text-4xl text-slate-100">--</span>
                                <span class="text-slate-500 font-medium text-xl mb-1">%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <footer class="mt-12 text-center text-slate-500 text-sm">
            <p>ASV Monitoring Dashboard &copy; 2026. Data streams via Serial USB to Laravel Reverb.</p>
        </footer>
    </div>

    <!-- Application Logic -->
    <script type="module">
        document.addEventListener('DOMContentLoaded', () => {
            const statusEl = document.getElementById('connection-status');
            const statusText = document.getElementById('status-text');
            const compassRing = document.getElementById('compass-ring');
            
            // Wait a moment for Echo to initialize
            setTimeout(() => {
                if (window.Echo) {
                    statusEl.classList.remove('disconnected');
                    statusText.textContent = 'Listening';
                    
                    window.Echo.channel('sensors')
                        .listen('SensorDataUpdated', (e) => {
                            const data = e.sensorData;
                            
                            // Power
                            document.getElementById('val-battery-percent').textContent = data.battery_percent !== null ? Math.round(data.battery_percent) : '--';
                            document.getElementById('val-voltage').textContent = data.voltage !== null ? parseFloat(data.voltage).toFixed(2) + ' V' : '-- V';
                            document.getElementById('val-current').textContent = data.current !== null ? parseFloat(data.current).toFixed(2) + ' A' : '-- A';
                            
                            // Navigation
                            document.getElementById('val-lat').textContent = data.latitude !== null ? parseFloat(data.latitude).toFixed(6) : '--';
                            document.getElementById('val-lng').textContent = data.longitude !== null ? parseFloat(data.longitude).toFixed(6) : '--';
                            document.getElementById('val-speed').textContent = data.speed !== null ? parseFloat(data.speed).toFixed(1) : '--';
                            document.getElementById('val-alt').textContent = data.altitude !== null ? parseFloat(data.altitude).toFixed(1) : '--';
                            document.getElementById('val-satellites').textContent = data.satellites !== null ? data.satellites + ' Sats' : '0 Sats';
                            
                            // Heading
                            if (data.heading !== null) {
                                document.getElementById('val-heading').textContent = parseFloat(data.heading).toFixed(1);
                                // Rotate the compass ring opposite to heading to simulate turning
                                compassRing.style.transform = `rotate(${-data.heading}deg)`;
                            }
                            
                            // Environment
                            document.getElementById('val-temp').textContent = data.temperature !== null ? parseFloat(data.temperature).toFixed(1) : '--';
                            document.getElementById('val-humidity').textContent = data.humidity !== null ? parseFloat(data.humidity).toFixed(1) : '--';
                            
                            // Flash connection indicator
                            statusEl.classList.remove('disconnected');
                            statusEl.classList.add('bg-emerald-500/20');
                            setTimeout(() => statusEl.classList.remove('bg-emerald-500/20'), 300);
                        });
                } else {
                    statusText.textContent = 'Disconnected (Echo not found)';
                }
            }, 1000);
        });
    </script>
</body>
</html>
