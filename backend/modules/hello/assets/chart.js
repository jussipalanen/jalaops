// A tiny bar chart library for the hello module's view: bars for the numbers
// and a dashed line at the average. Libraries a view uses are listed in
// "assets" in module.json and loaded before the view.
window.barChart = function barChart(svg, numbers, { average, format = String } = {}) {
  const width = 600
  const height = 160
  const max = Math.max(...numbers, 0) || 1
  const slot = width / Math.max(numbers.length, 1)
  const y = (value) => height - 20 - (Math.max(value, 0) / max) * (height - 30)
  const namespace = 'http://www.w3.org/2000/svg'

  function add(name, attributes, text) {
    const element = document.createElementNS(namespace, name)
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value))
    if (text !== undefined) element.textContent = text
    svg.append(element)
  }

  svg.classList.add('bar-chart')
  svg.setAttribute('viewBox', `0 0 ${width} ${height}`)
  svg.replaceChildren()

  numbers.forEach((value, index) => {
    const x = index * slot + slot * 0.15
    add('rect', { class: 'bar', x, y: y(value), width: slot * 0.7, height: height - 20 - y(value), rx: 4 })
    add('text', { x: x + slot * 0.35, y: height - 4, 'text-anchor': 'middle' }, format(value))
  })

  if (average !== undefined) {
    add('line', { class: 'average', x1: 0, x2: width, y1: y(average), y2: y(average) })
  }
}
