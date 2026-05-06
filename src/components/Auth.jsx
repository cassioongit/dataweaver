import React, { useState, useEffect } from 'react';
import { supabase } from '@/lib/supabase';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import Footer from './Footer';

export default function Auth({ isConfigured = true, configMessage = '' }) {
  const [loading, setLoading] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [mode, setMode] = useState('login'); // login, signup, forgot, update
  const [message, setMessage] = useState({ type: '', text: '' });
  const [showSuccessModal, setShowSuccessModal] = useState(false);
  const runtimeMessage =
    configMessage ||
    'Configure VITE_SUPABASE_URL e VITE_SUPABASE_PUBLISHABLE_KEY (ou VITE_SUPABASE_ANON_KEY) para habilitar a autenticacao.';

  const getAuthErrorMessage = (error) => {
    const message = error?.message || '';
    const normalizedMessage = message.toLowerCase();

    if (
      normalizedMessage.includes('failed to fetch') ||
      error?.name === 'AuthRetryableFetchError' ||
      error?.name === 'TypeError'
    ) {
      return 'Nao foi possivel conectar ao servico de autenticacao. Verifique a conexao e a configuracao do Supabase.';
    }

    if (message.includes('not allowed') || message.includes('domínio de e-mail não autorizado')) {
      return 'Apenas e-mails corporativos autorizados sao permitidos.';
    }

    if (error?.status === 429) {
      return 'Limite de e-mails atingido. Por favor, aguarde alguns minutos.';
    }

    return message || 'Ocorreu um erro na operacao.';
  };

  const getPasswordRecoveryRedirectUrl = () => {
    const configuredUrl = import.meta.env.VITE_AUTH_REDIRECT_URL?.trim();

    if (configuredUrl) {
      return configuredUrl;
    }

    return new URL(import.meta.env.BASE_URL, window.location.origin).toString();
  };

  useEffect(() => {
    if (!isConfigured) {
      return;
    }

    // Escuta eventos de recuperação de senha
    const { data: { subscription } } = supabase.auth.onAuthStateChange(async (event) => {
      if (event === "PASSWORD_RECOVERY") {
        setMode('update');
      }
    });

    return () => subscription.unsubscribe();
  }, [isConfigured]);

  const handleAuth = async (e) => {
    e.preventDefault();

    if (!isConfigured) {
      setMessage({ type: 'error', text: runtimeMessage });
      return;
    }

    setLoading(true);
    setMessage({ type: '', text: '' });

    try {
      if (mode === 'signup') {
        const { error } = await supabase.auth.signUp({
          email,
          password,
        });
        if (error) throw error;
        setShowSuccessModal(true);
        setMode('login');
        setPassword('');
      } else if (mode === 'login') {
        const { error } = await supabase.auth.signInWithPassword({
          email,
          password,
        });
        if (error) throw error;
      } else if (mode === 'forgot') {
        const { error } = await supabase.auth.resetPasswordForEmail(email, {
          redirectTo: getPasswordRecoveryRedirectUrl(),
        });
        if (error) throw error;
        setMessage({ type: 'success', text: 'Caso você seja um usuário registrado um e-mail de recuperação enviado! Verifique sua caixa de entrada.' });
      } else if (mode === 'update') {
        const { error } = await supabase.auth.updateUser({ password });
        if (error) throw error;
        setMessage({ type: 'success', text: 'Senha atualizada com sucesso! Você já pode acessar o sistema.' });
        setTimeout(() => setMode('login'), 2000);
      }
    } catch (error) {
      setMessage({ type: 'error', text: getAuthErrorMessage(error) });
    } finally {
      setLoading(false);
    }
  };

  const getSubtitle = () => {
    if (mode === 'signup') return 'Crie sua conta corporativa para acessar.';
    if (mode === 'forgot') return 'Enviaremos um link para resetar sua senha.';
    if (mode === 'update') return 'Digite sua nova senha de acesso.';
    return 'Insira suas credenciais para operar DATAWEAVER.';
  };

  const getTitle = () => {
    if (mode === 'signup') return 'Criar Conta';
    if (mode === 'forgot') return 'Recuperar Senha';
    if (mode === 'update') return 'Nova Senha';
    return 'Acesso Restrito';
  };

  return (
    <div className="min-h-screen bg-[#F1F3F5] flex flex-col relative overflow-hidden font-sans">
      
      {/* Background visual interest (optional gradient/mesh context if desired, simulating the subtle light of the design) */}
      <div className="absolute inset-0 bg-radial from-white/40 to-transparent pointer-events-none"></div>

      {/* Global Header Logo */}
      <div className="w-full flex-none flex flex-col items-center pt-[10vh] pb-8 z-10">
        <div className="flex items-center gap-3">
          <span className="material-symbols-outlined text-[32px] text-[#0061FF]">layers</span>
          <h1 className="text-3xl font-black text-[#1a1a1a] tracking-tight">Dataweaver</h1>
        </div>
        <p className="text-[11px] font-black text-zinc-500 uppercase tracking-[0.15em] mt-2">
          DATAWEAVER
        </p>
      </div>

      {/* Card Container */}
      <div className="flex-1 flex flex-col items-center z-10 w-full px-4">
        
        <div className="relative">
          <Card className="w-full sm:w-[420px] bg-white rounded-[16px] shadow-sm border-none overflow-hidden">
            <div className="px-8 pt-8 pb-4">
              <h2 className="text-xl font-black text-[#1a1a1a] tracking-tight">{getTitle()}</h2>
              <p className="text-zinc-500 text-sm mt-1 font-medium">
                {getSubtitle()}
              </p>
            </div>

            <CardContent className="px-8 pb-8">
              <form onSubmit={handleAuth} className="space-y-5">
                {!isConfigured && (
                  <div className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p className="font-black uppercase tracking-[0.12em] text-[10px]">Configuracao ausente</p>
                    <p className="mt-2 font-medium leading-relaxed">
                      {runtimeMessage}
                    </p>
                  </div>
                )}

                {message.text && (
                  <div className={`p-4 text-sm font-bold rounded-md ${message.type === 'error' ? 'bg-red-50 text-red-600 border border-red-100' : 'bg-green-50 text-green-600 border border-green-100'}`}>
                    {message.text}
                  </div>
                )}
                
                {mode !== 'update' && (
                  <div className="space-y-2">
                    <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest">E-mail Corporativo</label>
                    <div className="relative">
                      <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-[18px]">mail</span>
                      <Input
                        type="email"
                        placeholder="email@dominio.com"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                        disabled={!isConfigured}
                        className="h-12 pl-10 pr-4 rounded-md bg-zinc-200/60 border-none focus:bg-zinc-200 focus:ring focus:ring-[#0061FF]/10 transition-all font-medium text-zinc-600 disabled:opacity-60 disabled:cursor-not-allowed"
                      />
                    </div>
                  </div>
                )}

                {mode !== 'forgot' && (
                  <div className="space-y-2">
                    <div className="flex justify-between items-center">
                      <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest">
                        {mode === 'update' ? 'Nova Senha' : 'Senha'}
                      </label>
                      {mode === 'login' && (
                        <button 
                          type="button" 
                          onClick={() => setMode('forgot')}
                          disabled={!isConfigured}
                          className="text-[11px] font-bold text-[#0061FF] hover:text-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          Esqueceu a senha?
                        </button>
                      )}
                    </div>
                    <div className="relative">
                      <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-[18px]">lock</span>
                      <Input
                        type="password"
                        placeholder={mode === 'update' ? 'Digite a nova senha' : '••••••••'}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        required
                        disabled={!isConfigured}
                        className="h-12 pl-10 pr-4 rounded-md bg-zinc-200/60 border-none focus:bg-zinc-200 focus:ring focus:ring-[#0061FF]/10 transition-all font-black text-zinc-500 tracking-widest disabled:opacity-60 disabled:cursor-not-allowed"
                      />
                    </div>
                  </div>
                )}

                <Button
                  type="submit"
                  disabled={loading || !isConfigured}
                  className="w-full h-12 mt-2 bg-[#0061FF] hover:bg-blue-700 text-white font-bold text-[14px] rounded-md transition-all shadow-sm flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                  {loading ? (
                    <span className="flex items-center gap-2">
                      <span className="material-symbols-outlined animate-spin text-[18px]">sync</span> Processando...
                    </span>
                  ) : !isConfigured ? (
                    <span className="flex items-center gap-2">
                      <span className="material-symbols-outlined text-[18px]">warning</span>
                      Configuracao pendente
                    </span>
                  ) : (
                    <>
                      {mode === 'signup' ? 'Criar minha conta' : 
                       mode === 'forgot' ? 'Enviar Link' :
                       mode === 'update' ? 'Atualizar Senha' : 'Acessar Sistema'}
                      <span className="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </>
                  )}
                </Button>
                
              </form>
            </CardContent>
          </Card>

          {/* Under-card helper text removed as per request */}
        </div>

      </div>

      {/* Fixed Footer at the bottom */}
      <div className="fixed bottom-0 left-0 right-0 z-20">
        <Footer />
      </div>

      {showSuccessModal && (
        <div className="fixed inset-0 z-[120] flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-zinc-900/60 backdrop-blur-md" onClick={() => setShowSuccessModal(false)}></div>
          <div className="relative bg-white rounded-md shadow-2xl w-full max-w-sm p-10 text-center animate-fade-in border border-black/5">
             <div className="w-20 h-20 bg-green-100 text-green-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-sm">
               <span className="material-symbols-outlined text-4xl">mark_email_read</span>
             </div>
             <h2 className="text-2xl font-black text-zinc-900 mb-2 tracking-tight">
                Verifique seu E-mail
             </h2>
             <p className="text-zinc-500 font-medium mb-8 leading-relaxed text-sm">
                Conta criada com sucesso! Enviamos um e-mail de confirmação. Cheque sua caixa de entrada e clique no link para ativar seu acesso no <strong className="text-zinc-800">Dataweaver</strong>.
             </p>

             <Button 
                onClick={() => setShowSuccessModal(false)}
                className="w-full h-12 bg-green-600 text-white font-bold rounded-md hover:bg-green-700 shadow-sm transition-all text-[14px] border-0"
             >
                Entendido
             </Button>
          </div>
        </div>
      )}
    </div>
  );
}
