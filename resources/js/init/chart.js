import {
  Chart,
  BarController,
  BarElement,
  LineController,
  LineElement,
  PointElement,
  PieController,
  ArcElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
  Title
} from 'chart.js';

// Registrasi komponen yang dibutuhkan
Chart.register(
  BarController, BarElement,
  LineController, LineElement, PointElement,
  PieController, ArcElement,
  CategoryScale, LinearScale,
  Tooltip, Legend, Title
);

// Buat Chart global agar bisa diakses Alpine/Livewire
window.Chart = Chart;
