import { createClient } from '@supabase/supabase-js'

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL?.trim()
const supabasePublishableKey =
  import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY?.trim() ||
  import.meta.env.VITE_SUPABASE_ANON_KEY?.trim()

const isConfigured = Boolean(supabaseUrl && supabasePublishableKey)
const configurationMessage = isConfigured
  ? ''
  : 'Configure VITE_SUPABASE_URL e VITE_SUPABASE_PUBLISHABLE_KEY (ou VITE_SUPABASE_ANON_KEY) para habilitar a autenticacao.'

const stubAuth = {
  async getSession() {
    return { data: { session: null } }
  },
  onAuthStateChange(_callback) {
    return {
      data: {
        subscription: {
          unsubscribe() {},
        },
      },
    }
  },
  async signOut() {
    return { error: null }
  },
  async signInWithPassword() {
    return { error: new Error(configurationMessage) }
  },
  async signUp() {
    return { error: new Error(configurationMessage) }
  },
  async resetPasswordForEmail() {
    return { error: new Error(configurationMessage) }
  },
  async updateUser() {
    return { error: new Error(configurationMessage) }
  },
}

export const supabase = isConfigured
  ? createClient(supabaseUrl, supabasePublishableKey)
  : { auth: stubAuth }

export const supabaseIsConfigured = isConfigured
export const supabaseConfigMessage = configurationMessage
