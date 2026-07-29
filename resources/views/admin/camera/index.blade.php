@extends('layouts.admin')
@section('title','Kamera')
@section('content')
<div class="admin-camera-page">
    <div class="admin-camera-card">
        <div class="admin-camera-header">
            <h2>Kamera</h2>
            <div class="admin-camera-live">
                <span class="admin-live-dot"></span>
                Live
            </div>
        </div>
        {{-- Kamera Atas Laut --}}
        <div class="admin-camera-item">
            <div class="admin-camera-label">
                Area atas laut
            </div>

            <div class="admin-camera-frame">
                <video
                    id="camera-atas"
                    autoplay
                    playsinline
                    muted
                    style="width:100%;height:100%;object-fit:cover;">
                </video>
            </div>
            <div class="admin-camera-info">
                Live C922 Camera
            </div>
        </div>
        {{-- Kamera Bawah Laut --}}
        <div class="admin-camera-item">
            <div class="admin-camera-label">
                Area bawah laut
            </div>
            <div class="admin-camera-frame">
                <video
                    id="camera-bawah"
                    autoplay
                    playsinline
                    muted
                    style="width:100%;height:100%;object-fit:cover;">
                </video>
            </div>
            <div class="admin-camera-info">
                Live USB Camera
            </div>
        </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", async () => {

    try {

        // Minta izin kamera
        await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: false
        });

        // Device ID kamera
        const DEVICE_BAWAH = "ed8b5b0d75e7e3db3f4839c11399217ffe8d9d7ae4ed5bc217ba038c230472ff";
        const DEVICE_ATAS = "02fe8959a97e174d48a667ec0451e9d4cf13d79b9feccc39757896afd19ab4ba";
        // Kamera bawah
        const streamBawah = await navigator.mediaDevices.getUserMedia({
            video:{
                deviceId:{
                    exact: DEVICE_BAWAH
                },
                width:1280,
                height:720
            },
            audio:false
        });
        document.getElementById("camera-bawah").srcObject = streamBawah;
        // Kamera atas
        const streamAtas = await navigator.mediaDevices.getUserMedia({
            video:{
                deviceId:{
                    exact: DEVICE_ATAS
                },
                width:1280,
                height:720
            },
            audio:false
        });
        document.getElementById("camera-atas").srcObject = streamAtas;
    }
    catch(error){
        console.error(error);
        alert("Gagal menghubungkan kamera.");
    }
});
</script>
@endsection