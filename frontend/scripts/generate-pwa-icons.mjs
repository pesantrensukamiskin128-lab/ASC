/**
 * Generate PNG icons untuk PWA dari SVG menggunakan Canvas API Node.js
 * Jalankan: node scripts/generate-pwa-icons.mjs
 */
import { createCanvas } from 'canvas'
import { writeFileSync, mkdirSync } from 'fs'
import { join, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const outputDir = join(__dirname, '../public/icons')

mkdirSync(outputDir, { recursive: true })

function generateIcon(size) {
  const canvas = createCanvas(size, size)
  const ctx = canvas.getContext('2d')

  // Background rounded rect (biru)
  const radius = size * 0.1875 // ~96/512
  ctx.beginPath()
  ctx.moveTo(radius, 0)
  ctx.lineTo(size - radius, 0)
  ctx.quadraticCurveTo(size, 0, size, radius)
  ctx.lineTo(size, size - radius)
  ctx.quadraticCurveTo(size, size, size - radius, size)
  ctx.lineTo(radius, size)
  ctx.quadraticCurveTo(0, size, 0, size - radius)
  ctx.lineTo(0, radius)
  ctx.quadraticCurveTo(0, 0, radius, 0)
  ctx.closePath()
  ctx.fillStyle = '#2563eb'
  ctx.fill()

  // Teks "ASC"
  const fontSize = Math.round(size * 0.35)
  ctx.fillStyle = '#ffffff'
  ctx.font = `bold ${fontSize}px Arial`
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillText('ASC', size / 2, size / 2)

  const buffer = canvas.toBuffer('image/png')
  const filename = join(outputDir, `pwa-${size}x${size}.png`)
  writeFileSync(filename, buffer)
  console.log(`✓ Generated: ${filename}`)
}

generateIcon(192)
generateIcon(512)
console.log('PWA icons generated successfully.')
