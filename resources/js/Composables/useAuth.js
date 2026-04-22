import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export function useAuth() {
    const page = usePage()
    
    const user = computed(() => page.props.auth?.user)
    
    const can = (permission) => {
        if (!user.value) return false
        if (user.value.role?.name === 'Admin') return true
        return user.value.permissions?.includes(permission)
    }

    const canAny = (permissions) => {
        if (!user.value) return false
        if (user.value.role?.name === 'Admin') return true
        return permissions.some(permission => user.value.permissions?.includes(permission))
    }

    const hasRole = (roleName) => {
        if (!user.value) return false
        return user.value.role?.name === roleName
    }

    const fullName = computed(() => {
        if (!user.value) return ''
        return `${user.value.imie} ${user.value.nazwisko}`.trim() || user.value.email
    })

    const initials = computed(() => {
        if (!user.value) return '?'
        const first = user.value.imie?.charAt(0) || ''
        const last = user.value.nazwisko?.charAt(0) || ''
        return (first + last).toUpperCase() || user.value.email.charAt(0).toUpperCase()
    })

    return { user, can, canAny, hasRole, fullName, initials }
}
