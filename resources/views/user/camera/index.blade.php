@extends('layouts.user')
@section('title','Kamera')

@section('content')

<div class="user-camera-page">

    <div class="user-camera-card">

        <div class="user-camera-header">
            <h2>Kamera</h2>

            <div class="user-camera-live">
                <span class="user-live-dot"></span>
                Live
            </div>
        </div>

        {{-- Kamera Atas Air --}}
        <div class="user-camera-item">

            <div class="user-camera-label">
                Kamera Atas Air
            </div>

            <div class="user-camera-frame">

                <video
                    id="camera-atas"
                    autoplay
                    playsinline
                    muted
                    style="width:100%;height:100%;object-fit:cover;">
                </video>

            </div>

            <div class="user-camera-info">
                Live Camera
            </div>

        </div>

        {{-- Kamera Bawah Air --}}
        <div class="user-camera-item">

            <div class="user-camera-label">
                Kamera Bawah Air
            </div>

            <div class="user-camera-frame">

                <video
                    id="camera-bawah"
                    autoplay
                    playsinline
                    muted
                    style="width:100%;height:100%;object-fit:cover;">
                </video>

            </div>

            <div class="user-camera-info">
                Live Camera
            </div>

        </div>

    </div>

</div>

<script>

document.addEventListener("DOMContentLoaded", async () => {

    try {

        // Meminta izin kamera
        await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: false
        });

        // Device ID kamera
        const DEVICE_ATAS =
        "02fe8959a97e174d48a667ec0451e9d4cf13d79b9feccc39757896afd19ab4ba";

        const DEVICE_BAWAH =
        "ed8b5b0d75e7e3db3f4839c11399217ffe8d9d7ae4ed5bc217ba038c230472ff";

        // Kamera Atas
        const streamAtas = await navigator.mediaDevices.getUserMedia({

            video:{
                deviceId:{
                    exact:DEVICE_ATAS
                },
                width:1280,
                height:720
            },
            audio:false

        });

        document.getElementById("camera-atas").srcObject = streamAtas;

        // Kamera Bawah
        const streamBawah = await navigator.mediaDevices.getUserMedia({

            video:{
                deviceId:{
                    exact:DEVICE_BAWAH
                },
                width:1280,
                height:720
            },
            audio:false

        });

        document.getElementById("camera-bawah").srcObject = streamBawah;

    }
    catch(error){

        console.error(error);

        alert("Gagal menghubungkan kamera.");

    }

});

</script>

@endsection