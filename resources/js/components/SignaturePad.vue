<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from 'vue'

const props = withDefaults(defineProps<{
  initialImageUrl?: string | null
  disabled?: boolean
}>(), {
  initialImageUrl: null,
  disabled: false,
})

const emit = defineEmits<{
  (e: 'change', blob: Blob | null): void
}>()

const canvasRef = ref<HTMLCanvasElement | null>(null)
const isDrawing = ref(false)
let lastX = 0
let lastY = 0

function setupCanvas() {
  const canvas = canvasRef.value
  if (!canvas) return

  const ratio = window.devicePixelRatio || 1
  const cssWidth = canvas.clientWidth || 600
  const cssHeight = canvas.clientHeight || 180

  canvas.width = Math.floor(cssWidth * ratio)
  canvas.height = Math.floor(cssHeight * ratio)

  const ctx = canvas.getContext('2d')
  if (!ctx) return

  ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
  ctx.fillStyle = 'transparent'
  ctx.fillRect(0, 0, cssWidth, cssHeight)
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.lineWidth = 4
  ctx.strokeStyle = '#00008B'
}

function getPoint(ev: PointerEvent) {
  const canvas = canvasRef.value
  if (!canvas) return { x: 0, y: 0 }
  const rect = canvas.getBoundingClientRect()
  return {
    x: ev.clientX - rect.left,
    y: ev.clientY - rect.top,
  }
}

function onPointerDown(ev: PointerEvent) {
  if (props.disabled) return
  isDrawing.value = true
  const p = getPoint(ev)
  lastX = p.x
  lastY = p.y
}

function onPointerMove(ev: PointerEvent) {
  if (!isDrawing.value || props.disabled) return
  const canvas = canvasRef.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const p = getPoint(ev)
  ctx.beginPath()
  ctx.moveTo(lastX, lastY)
  ctx.lineTo(p.x, p.y)
  ctx.stroke()

  lastX = p.x
  lastY = p.y
}

function emitBlob() {
  const canvas = canvasRef.value
  if (!canvas) return
  canvas.toBlob((blob) => {
    emit('change', blob)
  }, 'image/png')
}

function onPointerUp() {
  if (!isDrawing.value) return
  isDrawing.value = false
  emitBlob()
}

function clear() {
  setupCanvas()
  emit('change', null)
}

async function drawInitialImage() {
  if (!props.initialImageUrl) {
    clear()
    return
  }

  const canvas = canvasRef.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  setupCanvas()

  const img = new Image()
  img.onload = () => {
    const w = canvas.clientWidth
    const h = canvas.clientHeight
    const ratio = Math.min(w / img.width, h / img.height)
    const drawW = img.width * ratio
    const drawH = img.height * ratio
    const x = (w - drawW) / 2
    const y = (h - drawH) / 2
    ctx.drawImage(img, x, y, drawW, drawH)
  }
  img.src = props.initialImageUrl
}

onMounted(async () => {
  await nextTick()
  setupCanvas()
  await drawInitialImage()
})

watch(() => props.initialImageUrl, async () => {
  await nextTick()
  await drawInitialImage()
})
</script>

<template>
  <div class="flex flex-col gap-2">
    <canvas
      ref="canvasRef"
      class="signature-canvas"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointerleave="onPointerUp"
    />
    <div class="flex justify-end">
      <Button
        type="button"
        label="Vymazať podpis"
        text
        class="text-accent! px-2!"
        :disabled="disabled"
        @click="clear"
      />
    </div>
  </div>
</template>

<style scoped>
.signature-canvas {
  width: 100%;
  height: 180px;
  border: 1px dashed #d1d5db;
  border-radius: 0.375rem;
  background: #ffffff;
  touch-action: none;
}
</style>
