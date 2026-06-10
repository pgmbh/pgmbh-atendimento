import api from './api'

const ENDPOINTS = {
  'service-types': '/service-types',
  priorities: '/priorities',
  sectors: '/sectors',
  tags: '/tags',
}

export const configService = {
  list(entity, params = {}) {
    return api.get(ENDPOINTS[entity], { params })
  },
  create(entity, data) {
    return api.post(ENDPOINTS[entity], data)
  },
  update(entity, id, data) {
    return api.put(`${ENDPOINTS[entity]}/${id}`, data)
  },
  toggleActive(entity, id) {
    return api.patch(`${ENDPOINTS[entity]}/${id}/toggle-active`)
  },
}
