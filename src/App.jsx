import React, { useState, useEffect, useRef } from 'react';
import { supabase, supabaseIsConfigured, supabaseConfigMessage } from '@/lib/supabase';
import Auth from '@/components/Auth';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import Footer from './components/Footer';

function App() {
  const [session, setSession] = useState(null);
  const [isInitializing, setIsInitializing] = useState(true);
  const [status, setStatus] = useState('Pronto para processar');
  const [progress, setProgress] = useState(0);
  const [stats, setStats] = useState({ totalRead: 0, divergences: 0 });
  const [patients, setPatients] = useState([]);
  const [downloadLink, setDownloadLink] = useState(null);
  const [isDownloading, setIsDownloading] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [currentView, setCurrentView] = useState('conciliacao'); // 'conciliacao' | 'logs' | 'pacientes'
  const [dbfRecords, setDbfRecords] = useState([]);
  const [dbfSearch, setDbfSearch] = useState('');
  const [dbfViewMode, setDbfViewMode] = useState('compact');
  const [selectedDbfRecord, setSelectedDbfRecord] = useState(null);
  const [showDbfDrawer, setShowDbfDrawer] = useState(false);
  const [dbfDrawerMode, setDbfDrawerMode] = useState('view'); // 'view' | 'edit'
  const [dbfDraft, setDbfDraft] = useState(null);
  const [dbfIsSaving, setDbfIsSaving] = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [showSuccessScreen, setShowSuccessScreen] = useState(false);
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [isDragging, setIsDragging] = useState(false);
  const [isLoadingDB, setIsLoadingDB] = useState(false);
  const [totalRecords, setTotalRecords] = useState(0);
  const [importHistory, setImportHistory] = useState([]);
  const [, setSystemLogs] = useState([]);
	  const [showWelcomeModal, setShowWelcomeModal] = useState(false);
	  const [showHistoryDetailModal, setShowHistoryDetailModal] = useState(false);
	  const [selectedHistoryItem, setSelectedHistoryItem] = useState(null);
	  const [historyQuery, setHistoryQuery] = useState('');
	  const [, setPreviouslyImported] = useState(null);
	  const [fileInputKey, setFileInputKey] = useState(0);
	  const [errorModal, setErrorModal] = useState(null);
	  const lastApiErrorKeyRef = useRef('');

	  // Supabase password recovery (forgot password) flow: show a modal even when user is already signed in via recovery token.
	  const [showPasswordRecoveryModal, setShowPasswordRecoveryModal] = useState(false);
	  const [recoveryPassword, setRecoveryPassword] = useState('');
	  const [recoveryPasswordConfirm, setRecoveryPasswordConfirm] = useState('');
	  const [recoveryIsSaving, setRecoveryIsSaving] = useState(false);
	  const [recoveryMessage, setRecoveryMessage] = useState({ type: '', text: '' });

  // Preview (dry-run) states
  const [previewData, setPreviewData] = useState(null);   // { new[], existing[], warnings[], file, total }
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [pendingFile, setPendingFile] = useState(null);   // File object waiting for confirm

  // Pagination and Sort states
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(20);
  const [navigatorPage, setNavigatorPage] = useState('');
  const [sortConfig, setSortConfig] = useState({ key: 'id', direction: 'desc' });

  const [, setSteps] = useState({
    validation: 'pending',
    extraction: 'pending',
    matching: 'pending'
  });

  const isLocalHostname =
    window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost';
  const apiOrigin = isLocalHostname ? (import.meta.env.VITE_API_ORIGIN || 'http://127.0.0.1:8888/') : '';
  const resolveApiUrl = (url) => {
    if (/^https?:\/\//i.test(url)) return url;
    if (apiOrigin) {
      return new URL(url.replace(/^\//, ''), apiOrigin.endsWith('/') ? apiOrigin : `${apiOrigin}/`).toString();
    }
    return url;
  };

  const buildAuthHeaders = (headers = {}) => {
    const nextHeaders = new Headers(headers);
    if (session?.access_token) {
      nextHeaders.set('Authorization', `Bearer ${session.access_token}`);
    }
    return nextHeaders;
  };

  const apiFetch = (url, options = {}) => {
    const { headers, ...rest } = options;
    return fetch(url, {
      ...rest,
      headers: buildAuthHeaders(headers),
    });
  };

  const sanitizeDbfValue = (value) => {
    if (value === null || value === undefined) return '';
    return String(value).trim();
  };

  const cleanDbfName = (record) => {
    const rawName = sanitizeDbfValue(record?.[1]);
    return rawName
      .replace(/^Ortodontia Vogel;?["']?|["']$/gi, '')
      .replace(/";".*$/g, '')
      .replace(/;.*$/g, '')
      .trim();
  };

  const getDbfInitials = (name) => {
    const words = sanitizeDbfValue(name)
      .split(' ')
      .filter((word) => word.length > 0);
    if (words.length === 0) return '??';
    return (words[0][0] + (words.length > 1 ? words[words.length - 1][0] : '')).toUpperCase();
  };

  const formatDbfDoc = (value) => {
    const raw = sanitizeDbfValue(value).replace(/\D+/g, '');
    if (!raw) return '—';
    if (raw.length >= 11) {
      return `${raw.substring(0, 3)}.***.***-${raw.substring(raw.length - 2)}`;
    }
    return sanitizeDbfValue(value) || '—';
  };

  const maskDbfText = (value, head = 10, tail = 4, mask = '****') => {
    const text = sanitizeDbfValue(value);
    if (!text || text === '—') return '—';
    const normalized = text.replace(/\s+/g, ' ').trim();
    if (normalized.length <= head + tail + mask.length) return normalized;
    return `${normalized.substring(0, head)}${mask}${normalized.substring(normalized.length - tail)}`;
  };

  const maskDbfAddress = (value) => {
    const text = sanitizeDbfValue(value);
    if (!text || text === '—') return '****';
    return maskDbfText(text, 10, 4, '****');
  };

  const formatDbfCep = (value) => sanitizeDbfValue(value) || '—';

  const formatDbfCityUf = (city, uf) => {
    const pieces = [sanitizeDbfValue(city), sanitizeDbfValue(uf)].filter(Boolean);
    return pieces.length > 0 ? pieces.join(' / ') : '—';
  };

  const formatDbfFieldValue = (record, index) => sanitizeDbfValue(record?.[index]) || '—';

  const openDbfDrawer = (record, rowNumber) => {
    const displayName = cleanDbfName(record);
    setSelectedDbfRecord({
      record,
      rowNumber,
      displayName,
      initials: getDbfInitials(displayName),
    });
    setDbfDrawerMode('view');
    setDbfDraft(null);
    setShowDbfDrawer(true);
  };

  const buildDbfDraftFromRecord = (record) => ({
    1: sanitizeDbfValue(record?.[1]),
    2: sanitizeDbfValue(record?.[2]),
    3: sanitizeDbfValue(record?.[3]),
    4: sanitizeDbfValue(record?.[4]),
    5: sanitizeDbfValue(record?.[5]),
    6: sanitizeDbfValue(record?.[6]),
    7: sanitizeDbfValue(record?.[7]),
    8: sanitizeDbfValue(record?.[8]),
    9: sanitizeDbfValue(record?.[9]),
    10: sanitizeDbfValue(record?.[10]),
    11: sanitizeDbfValue(record?.[11]),
    12: sanitizeDbfValue(record?.[12]),
    13: sanitizeDbfValue(record?.[13]),
  });

  const openDbfDrawerForEdit = (record, rowNumber) => {
    const displayName = cleanDbfName(record);
    setSelectedDbfRecord({
      record,
      rowNumber,
      displayName,
      initials: getDbfInitials(displayName),
    });
    setDbfDrawerMode('edit');
    setDbfDraft(buildDbfDraftFromRecord(record));
    setShowDbfDrawer(true);
  };

  const closeDbfDrawer = () => {
    setShowDbfDrawer(false);
    setSelectedDbfRecord(null);
    setDbfDrawerMode('view');
    setDbfDraft(null);
    setDbfIsSaving(false);
  };

  const saveDbfEdits = async () => {
    if (!selectedDbfRecord || !dbfDraft || dbfIsSaving) return;

    const dbIndex =
      selectedDbfRecord.record?._db_index ??
      selectedDbfRecord.record?.['_db_index'] ??
      null;

    if (!dbIndex) {
      openErrorModal('Falha ao salvar', 'Não foi possível identificar o índice do registro no DBF.');
      return;
    }

    setDbfIsSaving(true);

    try {
      const response = await apiFetch(resolveApiUrl('api/src/update-dbf-record.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          db_index: dbIndex,
          fields: dbfDraft,
        }),
      });

      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (_) {
        throw new Error('Resposta inválida do servidor.');
      }

      if (data.status === 'success' || data.status === 'noop') {
        const updatedRecord = {
          ...selectedDbfRecord.record,
          ...Object.fromEntries(Object.entries(dbfDraft)),
        };

        const nextName = cleanDbfName(updatedRecord);
        setSelectedDbfRecord((prev) => prev ? ({
          ...prev,
          record: updatedRecord,
          displayName: nextName,
          initials: getDbfInitials(nextName),
        }) : prev);

        setDbfDrawerMode('view');
        setDbfDraft(null);

        // Refresh list to keep the table consistent with the DBF on disk.
        fetchDBData(currentPage, pageSize, dbfSearch, sortConfig);
      } else {
        openErrorModal('Falha ao salvar', data.erro || 'Não foi possível salvar a alteração.');
      }
    } catch (err) {
      console.error('Erro ao salvar edição do DBF:', err);
      openErrorModal('Falha ao salvar', 'Não foi possível salvar agora. Tente novamente em instantes.');
    } finally {
      setDbfIsSaving(false);
    }
  };

  const dbfCompactColumns = [
    {
      key: 'index',
      header: '#',
      thClassName: 'px-4 py-3 text-[10px] font-black text-zinc-300 uppercase tracking-widest bg-zinc-50/50 w-10 text-center border-r border-black/[0.03]',
      tdClassName: 'px-4 py-2 text-center bg-zinc-50/50 border-r border-black/[0.03]',
      render: (_record, rowNumber) => (
        <span className="text-[10px] font-mono font-bold text-zinc-300">
          {rowNumber}
        </span>
      ),
    },
    {
      key: 'initials',
      header: 'Iniciais',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record, _rowNumber, _rowIndex, colorClass) => {
        const name = cleanDbfName(record);
        return (
          <div className={cn("w-6 h-6 rounded flex items-center justify-center font-black text-[8px]", colorClass)}>
            {getDbfInitials(name)}
          </div>
        );
      },
    },
    {
      key: 'name',
      header: 'Nome do Paciente',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => {
        const name = cleanDbfName(record);
        return <p className="text-[12px] font-black text-zinc-800 tracking-tight leading-tight">{name || 'REGISTRO SEM NOME'}</p>;
      },
    },
    {
      key: 'responsible',
      header: 'Responsável',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 truncate">{formatDbfFieldValue(record, 2)}</p>,
    },
    {
      key: 'address',
      header: 'Endereço',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 truncate max-w-[280px]">{maskDbfAddress(record?.[3])}</p>,
    },
    {
      key: 'doc',
      header: 'Documento (Doc)',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 font-mono tracking-tight">{formatDbfDoc(record?.[7])}</p>,
    },
    {
      key: 'cep',
      header: 'CEP',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 font-mono tracking-tight">{formatDbfCep(record?.[6])}</p>,
    },
    {
      key: 'actions',
      header: 'Ações',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white text-center',
      tdClassName: 'px-6 py-2 text-center',
      render: (record, rowNumber) => (
        <div className="flex items-center justify-center gap-1.5">
          <button
            type="button"
            onClick={(event) => {
              event.stopPropagation();
              openDbfDrawerForEdit(record, rowNumber);
            }}
            className="w-8 h-8 rounded-md flex items-center justify-center text-zinc-300 hover:text-[#0061FF] hover:bg-blue-50 transition-colors"
            aria-label="Editar registro"
            title="Editar"
          >
            <span className="material-symbols-outlined text-[18px]">edit</span>
          </button>
          <button
            type="button"
            onClick={(event) => {
              event.stopPropagation();
              openDbfDrawer(record, rowNumber);
            }}
            className="w-8 h-8 rounded-md flex items-center justify-center text-zinc-300 hover:text-zinc-700 hover:bg-zinc-50 transition-colors"
            aria-label="Abrir detalhes"
            title="Detalhes"
          >
            <span className="material-symbols-outlined text-[18px]">more_horiz</span>
          </button>
        </div>
      ),
    },
  ];

  const dbfCompleteColumns = [
    ...dbfCompactColumns.slice(0, 7),
    {
      key: 'street',
      header: 'Endereço Res.',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 truncate max-w-[260px]">{maskDbfAddress(record?.[3])}</p>,
    },
    {
      key: 'city_state',
      header: 'Cidade / UF Res.',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 truncate">{formatDbfCityUf(record?.[4], record?.[5])}</p>,
    },
    {
      key: 'street_com',
      header: 'Endereço Com.',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 truncate max-w-[260px]">{maskDbfAddress(record?.[8])}</p>,
    },
    {
      key: 'city_state_com',
      header: 'Cidade / UF Com.',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 truncate">{formatDbfCityUf(record?.[9], record?.[10])}</p>,
    },
    {
      key: 'cep_com',
      header: 'CEP Com.',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 font-mono tracking-tight">{formatDbfCep(record?.[11])}</p>,
    },
    {
      key: 'invoice',
      header: 'Envio Boleto',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 truncate">{formatDbfFieldValue(record, 12)}</p>,
    },
    {
      key: 'drdra',
      header: 'DR/DRA',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white',
      tdClassName: 'px-6 py-2',
      render: (record) => <p className="text-[11px] font-medium text-zinc-500 truncate">{formatDbfFieldValue(record, 13)}</p>,
    },
    {
      key: 'actions',
      header: 'Ações',
      thClassName: 'px-6 py-3 text-[10px] font-black text-zinc-400 uppercase tracking-widest bg-white text-center',
      tdClassName: 'px-6 py-2 text-center',
      render: (record, rowNumber) => (
        <div className="flex items-center justify-center gap-1.5">
          <button
            type="button"
            onClick={(event) => {
              event.stopPropagation();
              openDbfDrawerForEdit(record, rowNumber);
            }}
            className="w-8 h-8 rounded-md flex items-center justify-center text-zinc-300 hover:text-[#0061FF] hover:bg-blue-50 transition-colors"
            aria-label="Editar registro"
            title="Editar"
          >
            <span className="material-symbols-outlined text-[18px]">edit</span>
          </button>
          <button
            type="button"
            onClick={(event) => {
              event.stopPropagation();
              openDbfDrawer(record, rowNumber);
            }}
            className="w-8 h-8 rounded-md flex items-center justify-center text-zinc-300 hover:text-zinc-700 hover:bg-zinc-50 transition-colors"
            aria-label="Abrir detalhes"
            title="Detalhes"
          >
            <span className="material-symbols-outlined text-[18px]">more_horiz</span>
          </button>
        </div>
      ),
    },
  ];

  const activeDbfColumns = dbfViewMode === 'complete' ? dbfCompleteColumns : dbfCompactColumns;

  const dbfDetailGroups = [
    {
      title: 'Identificação',
      description: 'Dados centrais do cadastro.',
      icon: 'badge',
      fields: [
        { label: 'NCAD', value: (record) => formatDbfFieldValue(record, 0) },
        { label: 'Nome do Paciente', value: (record) => cleanDbfName(record) || '—' },
        { label: 'Responsável', value: (record) => formatDbfFieldValue(record, 2) },
        { label: 'Documento', value: (record) => formatDbfDoc(record?.[7]) },
      ],
    },
    {
      title: 'Residencial',
      description: 'Endereço e localização principal.',
      icon: 'home',
      fields: [
        { label: 'Endereço', value: (record) => maskDbfAddress(record?.[3]) },
        { label: 'Cidade', value: (record) => formatDbfFieldValue(record, 4) },
        { label: 'UF', value: (record) => formatDbfFieldValue(record, 5) },
        { label: 'CEP', value: (record) => formatDbfCep(record?.[6]) },
      ],
    },
    {
      title: 'Comercial',
      description: 'Dados do endereço comercial.',
      icon: 'business',
      fields: [
        { label: 'Endereço Comercial', value: (record) => maskDbfAddress(record?.[8]) },
        { label: 'Cidade Comercial', value: (record) => formatDbfFieldValue(record, 9) },
        { label: 'UF Comercial', value: (record) => formatDbfFieldValue(record, 10) },
        { label: 'CEP Comercial', value: (record) => formatDbfCep(record?.[11]) },
      ],
    },
    {
      title: 'Complementos',
      description: 'Campos operacionais e de atendimento.',
      icon: 'description',
      fields: [
        { label: 'Envio Boleto', value: (record) => formatDbfFieldValue(record, 12) },
        { label: 'DR/DRA', value: (record) => formatDbfFieldValue(record, 13) },
      ],
    },
  ];

	  const openErrorModal = (title, message) => {
	    setErrorModal({ title, message });
	  };

	  const openApiErrorModalOnce = (key, title, message) => {
	    if (errorModal) return;
	    if (lastApiErrorKeyRef.current === key) return;
	    lastApiErrorKeyRef.current = key;
	    openErrorModal(title, message);
	  };

	  const readJsonSafe = async (response) => {
	    try {
	      return await response.json();
	    } catch (_) {
	      return null;
	    }
	  };

	  const isRecoveryUrl = () => {
	    const hash = window.location.hash || '';
	    const search = window.location.search || '';
	    return hash.includes('type=recovery') || search.includes('type=recovery');
	  };

	  const clearRecoveryUrl = () => {
	    // Remove tokens/fragments so the modal doesn't reopen on refresh.
	    try {
	      window.history.replaceState(null, document.title, window.location.pathname + window.location.search);
	    } catch (_) {}
	  };

	  const submitRecoveryPassword = async () => {
	    if (recoveryIsSaving) return;

	    const next = String(recoveryPassword || '').trim();
	    const confirm = String(recoveryPasswordConfirm || '').trim();

	    if (!next || next.length < 8) {
	      setRecoveryMessage({ type: 'error', text: 'A nova senha precisa ter pelo menos 8 caracteres.' });
	      return;
	    }
	    if (next !== confirm) {
	      setRecoveryMessage({ type: 'error', text: 'As senhas não coincidem. Verifique e tente novamente.' });
	      return;
	    }

	    setRecoveryIsSaving(true);
	    setRecoveryMessage({ type: '', text: '' });
	    try {
	      const { error } = await supabase.auth.updateUser({ password: next });
	      if (error) throw error;

	      setRecoveryMessage({ type: 'success', text: 'Senha atualizada com sucesso.' });
	      clearRecoveryUrl();
	      window.setTimeout(() => {
	        setShowPasswordRecoveryModal(false);
	        setRecoveryPassword('');
	        setRecoveryPasswordConfirm('');
	        setRecoveryMessage({ type: '', text: '' });
	      }, 800);
	    } catch (err) {
	      const msg = err?.message || 'Não foi possível atualizar sua senha agora. Tente novamente.';
	      setRecoveryMessage({ type: 'error', text: msg });
	    } finally {
	      setRecoveryIsSaving(false);
	    }
	  };

  const appendBackendReason = (message, rawMessage = '') => {
    const reason = String(rawMessage || '').trim();
    if (!reason) return message;
    if (message.includes(reason)) return message;
    return `${message} (${reason})`;
  };

  const getPreviewErrorMessage = (rawMessage = '') => {
    if (!rawMessage) {
      return 'Não foi possível gerar a pré-visualização do arquivo. Verifique o formato e tente novamente.';
    }

    if (
      rawMessage.startsWith('Tipo de arquivo não permitido') ||
      rawMessage.startsWith('Nenhum arquivo válido enviado') ||
      rawMessage.startsWith('Formato de arquivo inválido')
    ) {
      return appendBackendReason(
        'O arquivo enviado é parecido com `12_2025-09-01 00_00_00_2026-04-10 23_59_00.csv`, mas não é o relatório esperado. Envie o CSV correto do Medkey e tente novamente.',
        rawMessage
      );
    }

    return appendBackendReason(
      'Não foi possível gerar a pré-visualização do arquivo. Verifique o formato e tente novamente.',
      rawMessage
    );
  };

  const formatDbfDetails = (details = {}) => {
    if (!details || typeof details !== 'object') return '';

    const summary = [
      details.versionHex ? `versao=${details.versionHex}` : null,
      Number.isFinite(details.headerSize) ? `header=${details.headerSize}` : null,
      Number.isFinite(details.recordSize) ? `registro=${details.recordSize}` : null,
      Number.isFinite(details.numRecords) ? `registros=${details.numRecords}` : null,
      Number.isFinite(details.fileSize) ? `arquivo=${details.fileSize}` : null,
      Array.isArray(details.issues) && details.issues.length > 0 ? `issues=${details.issues.join(',')}` : null,
    ].filter(Boolean);

    return summary.length > 0 ? ` [${summary.join(' | ')}]` : '';
  };

  const getImportErrorMessage = (rawMessage = '', details = null) => {
    if (!rawMessage) {
      return 'Não foi possível concluir a importação. Tente novamente.';
    }

    if (
      rawMessage.startsWith('Nenhum arquivo válido enviado') ||
      rawMessage.startsWith('Tipo de arquivo não permitido') ||
      rawMessage.startsWith('Falha ao realizar backup da base') ||
      rawMessage.startsWith('Falha ao publicar a cópia de trabalho da base DBF')
    ) {
      return rawMessage;
    }

    if (rawMessage.startsWith('A base DBF precisa manter o formato legado original')) {
      return appendBackendReason(rawMessage, formatDbfDetails(details));
    }

    return appendBackendReason(
      'Não foi possível concluir a importação. Tente novamente.',
      rawMessage
    );
  };

  const downloadCurrentDbf = async () => {
    if (isDownloading) return;

    setIsDownloading(true);
    try {
      const response = await apiFetch(resolveApiUrl('api/src/download.php'));
      if (!response.ok) {
        let message = 'Nao foi possivel baixar a base atualizada.';
        try {
          const data = await response.json();
          message = data.erro || data.message || message;
        } catch (_) {}
        throw new Error(message);
      }

      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'RELAT_orto.DBF';
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.setTimeout(() => window.URL.revokeObjectURL(url), 1000);
    } catch (err) {
      openErrorModal(
        'Falha no download',
        'Não foi possível baixar a base atualizada agora. Tente novamente em alguns instantes.'
      );
      console.error('Erro ao baixar a base atualizada:', err);
    } finally {
      setIsDownloading(false);
    }
  };

  const handleFileUpload = (e) => {
    const files = e.target.files;
    if (files.length === 0) return;
    startPreview(files[0]);
    setFileInputKey(k => k + 1);
  };

  const handleDragOver = (e) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setIsDragging(false);
    const files = e.dataTransfer.files;
    if (files.length > 0) {
      startPreview(files[0]);
    }
  };

  // ── STEP 1: Dry-run preview (does NOT write to DBF) ──────────────────────
  const startPreview = async (file) => {
    if (isPreviewing || isProcessing) return;
    setIsPreviewing(true);
    setPreviewData(null);
    setPendingFile(file);
    setStatus('Analisando arquivo...');

    const formData = new FormData();
    formData.append('upl', file);

    try {
      const response = await apiFetch(resolveApiUrl('api/src/preview.php'), { method: 'POST', body: formData });
      const text = await response.text();
      let data;
      try { data = JSON.parse(text); }
      catch (e) { throw new Error('Resposta inválida do servidor de preview.'); }

      if (data.status === 'preview') {
        setPreviewData(data);
      } else {
        openErrorModal(
          'Pré-visualização indisponível',
          getPreviewErrorMessage(data.erro)
        );
        console.error('Erro no preview:', data.erro || 'Falha desconhecida');
        setPendingFile(null);
      }
    } catch (err) {
      openErrorModal(
        'Falha no preview',
        getPreviewErrorMessage(err?.message || '')
      );
      console.error('Erro ao gerar preview:', err);
      setPendingFile(null);
    } finally {
      setIsPreviewing(false);
    }
  };

  // ── STEP 2: Actual import (writes to DBF) ────────────────────────────────
  const confirmImport = async () => {
    if (!pendingFile || isProcessing) return;

    const previousImport = importHistory.find(h => h.file_name === pendingFile.name);
    setPreviouslyImported(previousImport || null);

    setIsProcessing(true);
    setPreviewData(null);
    setDownloadLink(null);
    setProgress(0);
    setStatus('Iniciando importação...');
    setSteps({ validation: 'active', extraction: 'pending', matching: 'pending' });

    const formData = new FormData();
    formData.append('upl', pendingFile);

    try {
      await new Promise(r => setTimeout(r, 600));
      setSteps(s => ({ ...s, validation: 'done', extraction: 'active' }));
      setProgress(20);
      setStatus('Criando backup do DBF...');

      await new Promise(r => setTimeout(r, 800));
      setProgress(40);
      setStatus('Validando metadados...');

      await new Promise(r => setTimeout(r, 600));
      setSteps(s => ({ ...s, extraction: 'done', matching: 'active' }));
      setProgress(60);
      setStatus('Importando e conciliando registros...');

      const response = await apiFetch(resolveApiUrl('api/src/upload.php'), { method: 'POST', body: formData });
      const text = await response.text();
      let data;
      try { data = JSON.parse(text); }
      catch (e) { throw new Error('Resposta do servidor inválida. Verifique logs.'); }

        if (data.status === 'success') {
          setProgress(100);
          setStatus('Sincronismo Concluído');
          setSteps(s => ({ ...s, matching: 'done' }));
          setStats({ totalRead: data.total_read, divergences: data.divergences, added: data.added, updated: data.updated });
          
          if (Array.isArray(data.patients_added)) {
            const newPatients = data.patients_added.map(p => ({
                nome: p.nome || 'PACIENTE SEM NOME',
                id: p.id,
                type: p.type || 'new',
                truncated: p.truncated || false,
                initials: (p.nome && p.nome.length >= 2) ? p.nome.substring(0,2).toUpperCase() : '??',
                color: (p.type === 'update') ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'
            }));
            setPatients(newPatients);
          }
          
          setDownloadLink(data.file);
          setIsProcessing(false);
          setShowSuccessScreen(true);
          fetchHistory();
        } else {
          openErrorModal(
            'Importação não concluída',
            getImportErrorMessage(data.erro, data.details)
          );
          console.error('Erro na importação:', data.erro || 'Falha desconhecida');
          setIsProcessing(false);
        }
    } catch (err) {
      openErrorModal(
        'Importação não concluída',
        getImportErrorMessage(err?.message || '')
      );
      console.error('Erro ao concluir importação:', err);
      setIsProcessing(false);
    } finally {
      fetchLogs();
      setPendingFile(null);
    }
  };

  const cancelPreview = async () => {
    const previewFileName = previewData?.file || pendingFile?.name || '';
    const auditFile = previewData?.audit_file || null;

    try {
      if (previewFileName || auditFile) {
        await apiFetch(resolveApiUrl('api/src/cancel-preview.php'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            file_name: previewFileName,
            audit_file: auditFile,
          }),
        });
      }
    } catch (err) {
      console.warn('Falha ao limpar preview cancelado:', err);
    } finally {
      setPreviewData(null);
      setPendingFile(null);
      setStatus('Pronto para processar');
      setProgress(0);
      setSteps({ validation: 'pending', extraction: 'pending', matching: 'pending' });
      setFileInputKey(k => k + 1);
    }
  };

  const fetchLogs = async () => {
    try {
      const response = await apiFetch(resolveApiUrl('api/src/get-logs.php'));
      const data = await response.json();
      setSystemLogs(data);
    } catch (err) {}
  };

  // Effect for fetching DB data when pagination, page size, search or sort changes
  useEffect(() => {
    if (currentView === 'pacientes') {
      const timer = setTimeout(() => {
        fetchDBData(currentPage, pageSize, dbfSearch, sortConfig);
        setNavigatorPage(currentPage.toString());
      }, dbfSearch ? 300 : 0); // Debounce if searching
      return () => clearTimeout(timer);
    }
  }, [currentPage, pageSize, dbfSearch, sortConfig, currentView]);



	  const fetchDBData = async (page = 1, limit = 50, search = '', sort = { key: 'id', direction: 'desc' }) => {
	    setIsLoadingDB(true);
	    try {
	      const query = `q=${encodeURIComponent(search)}&page=${page}&limit=${limit}&sort_by=${sort.key}&order=${sort.direction}`;
	      const response = await apiFetch(resolveApiUrl(`api/src/get-dbf-data.php?${query}`));
	      const data = await readJsonSafe(response);

	      if (!response.ok || (data && data.status === 'error')) {
	        const rawMessage = data?.erro || data?.message || '';
	        const status = response.status || 0;
	        const key = `get-dbf-data:${status}:${rawMessage}`;
	        const title = status === 401 ? 'Acesso negado' : 'Falha ao carregar DBF';
	        const message =
	          status === 401
	            ? 'Não foi possível carregar a base DBF porque a API negou seu acesso. Faça login novamente e recarregue a página.'
	            : 'Não foi possível carregar a base DBF agora. Verifique se a API está rodando e tente novamente.';

	        openApiErrorModalOnce(key, title, rawMessage ? `${message} (${rawMessage})` : message);
	        return;
	      }

	      if (data?.status === 'success') {
	        setDbfRecords(data.data);
	        setTotalRecords(data.total);
	      }
	    } catch (err) {
	      openApiErrorModalOnce(
	        'get-dbf-data:network',
	        'Falha ao carregar DBF',
	        'Não foi possível carregar a base DBF agora. Verifique sua conexão e tente novamente.'
	      );
	      console.error('Error fetching DB data:', err);
	    } finally {
	      setIsLoadingDB(false);
	    }
	  };

  const handleSort = (key) => {
    setSortConfig(prev => ({
      key,
      direction: prev.key === key && prev.direction === 'asc' ? 'desc' : 'asc'
    }));
    setCurrentPage(1); // Reset to first page on sort
  };

	  const fetchHistory = async () => {
	    try {
	      const response = await apiFetch(resolveApiUrl('api/src/get-history.php'));
	      const data = await readJsonSafe(response);

	      if (!response.ok || (data && data.status === 'error')) {
	        const rawMessage = data?.erro || data?.message || '';
	        const status = response.status || 0;
	        const key = `get-history:${status}:${rawMessage}`;
	        const title = status === 401 ? 'Acesso negado' : 'Falha ao carregar histórico';
	        const message =
	          status === 401
	            ? 'Não foi possível carregar o histórico porque a API negou seu acesso. Faça login novamente e recarregue a página.'
	            : 'Não foi possível carregar o histórico agora. Verifique se a API está rodando e tente novamente.';

	        openApiErrorModalOnce(key, title, rawMessage ? `${message} (${rawMessage})` : message);
	        return;
	      }

	      setImportHistory(Array.isArray(data) ? data : []);
	    } catch (err) {
	      openApiErrorModalOnce(
	        'get-history:network',
	        'Falha ao carregar histórico',
	        'Não foi possível carregar o histórico agora. Verifique sua conexão e tente novamente.'
	      );
	    }
	  };

  const totalPages = Math.ceil(totalRecords / pageSize);

  useEffect(() => {
    if (session) {
      fetchDBData(currentPage, pageSize);
    }
  }, [currentPage, pageSize, session]);

  useEffect(() => {
    if (session) {
      fetchHistory();
    }
  }, [session]);

	  useEffect(() => {
	    if (session && importHistory.length > 0 && !sessionStorage.getItem('welcomeShown')) {
	      setShowWelcomeModal(true);
	      sessionStorage.setItem('welcomeShown', 'true');
	    }
	  }, [session, importHistory]);

	  useEffect(() => {
	    if (session && isRecoveryUrl()) {
	      setShowPasswordRecoveryModal(true);
	    }
	  }, [session]);

  useEffect(() => {
    if (!supabaseIsConfigured) {
      setSession(null);
      setIsInitializing(false);
      return;
    }

	    supabase.auth.getSession().then(({ data: { session } }) => {
	      setSession(session);
	      setIsInitializing(false);
	      if (session && isRecoveryUrl()) {
	        setShowPasswordRecoveryModal(true);
	      }
	    });

	    const {
	      data: { subscription },
	    } = supabase.auth.onAuthStateChange((_event, session) => {
	      if (_event === 'PASSWORD_RECOVERY') {
	        setShowPasswordRecoveryModal(true);
	      }
	      setSession(session);
	    });

    return () => subscription.unsubscribe();
  }, []);

  useEffect(() => {
    if (!isSidebarOpen) {
      return;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        setIsSidebarOpen(false);
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isSidebarOpen]);

  useEffect(() => {
    if (currentView !== 'pacientes') {
      closeDbfDrawer();
    }
  }, [currentView]);

  useEffect(() => {
    if (!showDbfDrawer || !selectedDbfRecord) {
      return;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        closeDbfDrawer();
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [showDbfDrawer, selectedDbfRecord]);

  if (isInitializing) {
    return <div className="min-h-screen bg-[#F1F3F5] flex items-center justify-center"><div className="w-12 h-12 border-4 border-blue-100 border-t-blue-500 rounded-full animate-spin"></div></div>;
  }

  if (!session) {
    return <Auth isConfigured={supabaseIsConfigured} configMessage={supabaseConfigMessage} />;
  }

  return (
    <div className="min-h-[100dvh] bg-[#F1F3F5] font-sans antialiased text-zinc-900 flex">
      {/* Main App Container - Full Bleed */}
      <div className="w-full min-h-[100dvh] md:h-screen flex relative">
        {isSidebarOpen && (
          <button
            type="button"
            aria-label="Fechar navegação"
            onClick={() => setIsSidebarOpen(false)}
            className="fixed inset-0 z-30 bg-zinc-900/45 backdrop-blur-[2px] md:hidden"
          />
        )}
        
        {/* Sidebar Navigation */}
        <aside className={cn(
          "fixed inset-y-0 left-0 z-40 w-72 max-w-[85vw] bg-white border-r border-black/5 flex flex-col select-none overflow-hidden transition-transform duration-300 ease-out shadow-2xl md:shadow-none md:translate-x-0 md:static md:z-20 md:w-[88px] md:shrink-0",
          isSidebarOpen ? "translate-x-0" : "-translate-x-full md:translate-x-0"
        )}>
          <div className="flex items-center justify-between px-4 py-4 border-b border-black/5 md:hidden">
            <div className="flex items-center gap-3 min-w-0">
              <div className="w-10 h-10 rounded-lg bg-[#0061FF] flex items-center justify-center text-white shrink-0 shadow-sm border border-white/10">
                <span className="material-symbols-outlined text-xl">layers</span>
              </div>
              <div className="min-w-0">
                <p className="font-black text-[#0061FF] text-sm tracking-tight uppercase leading-none truncate">Dataweaver</p>
                <p className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.16em] mt-1 truncate">DATAWEAVER</p>
              </div>
            </div>
            <button
              type="button"
              onClick={() => setIsSidebarOpen(false)}
              className="w-10 h-10 rounded-full bg-zinc-50 text-zinc-500 hover:text-zinc-800 hover:bg-zinc-100 transition-colors flex items-center justify-center"
              aria-label="Fechar menu"
            >
              <span className="material-symbols-outlined text-[20px]">close</span>
            </button>
          </div>

          <div className="flex-1 px-3 pt-4 md:px-2 md:pt-6">
            <nav className="space-y-1">
              {[
                { id: 'conciliacao', label: 'Home', icon: 'home' },
                { id: 'pacientes', label: 'DBF', icon: 'database' },
                { id: 'logs', label: 'Histórico', icon: 'history', onClick: fetchHistory },
                { id: 'suporte', label: 'Suporte', icon: 'help' }
              ].map((item) => (
                <button 
                  key={item.id}
                  onClick={() => {
                    setCurrentView(item.id);
                    if (item.onClick) item.onClick();
                    setIsSidebarOpen(false);
                  }} 
                  className={cn(
                    "w-full flex items-center justify-start gap-3 px-4 py-4 rounded-xl transition-all duration-200 group relative md:flex-col md:justify-center md:gap-1 md:px-0",
                    currentView === item.id 
                      ? "bg-[#0061FF]/10 text-[#0061FF]" 
                      : "text-zinc-400 hover:text-zinc-600 hover:bg-zinc-50"
                  )}
                >
                  <span className={cn(
                    "material-symbols-outlined text-[24px] mb-1.5 transition-transform duration-200",
                    currentView === item.id ? "font-variation-fill" : "group-hover:scale-110"
                  )}>
                    {item.icon}
                  </span>
                  <span className={cn(
                    "text-[13px] font-black tracking-tight md:text-[11px]",
                    currentView === item.id ? "" : "font-bold"
                  )}>
                    {item.label}
                  </span>
                </button>
              ))}
            </nav>
          </div>

          <div className="p-3 border-t border-black/5 space-y-1 mt-auto pb-4 md:p-2">
             <button 
               onClick={() => {
                 supabase.auth.signOut();
                 setIsSidebarOpen(false);
               }} 
               className="w-full flex items-center justify-start gap-3 px-4 py-4 rounded-xl text-zinc-400 hover:text-[#0061FF] hover:bg-blue-50 transition-all group md:flex-col md:justify-center md:gap-1 md:px-0"
               title="Sair do sistema"
             >
               <span className="material-symbols-outlined text-[24px] group-hover:scale-110 transition-transform">exit_to_app</span>
               <span className="text-[13px] font-black tracking-tight md:text-[11px]">Sair</span>
             </button>
          </div>
        </aside>

        {/* Main Content Area */}
        <div className="flex-1 flex flex-col bg-[#F1F3F5] min-h-[100dvh] md:h-screen overflow-hidden min-w-0 relative">
          
          {/* Top Header - Dynamic Brand + Avatar */}
          <header className="w-full h-16 sm:h-20 flex items-center justify-between px-4 sm:px-8 z-10 shrink-0 gap-3">
            <div className="flex items-center gap-3 flex-1 min-w-0">
              <button
                type="button"
                onClick={() => setIsSidebarOpen(true)}
                className="md:hidden w-10 h-10 rounded-lg bg-white border border-black/5 text-zinc-500 shadow-sm flex items-center justify-center"
                aria-label="Abrir menu"
              >
                <span className="material-symbols-outlined text-[20px]">menu</span>
              </button>
              <div className="flex items-center gap-3 animate-in fade-in slide-in-from-left-4 duration-500 min-w-0">
                  <div className="w-9 h-9 rounded-lg bg-[#0061FF] flex items-center justify-center text-white shrink-0 shadow-sm border border-white/10">
                    <span className="material-symbols-outlined text-xl">layers</span>
                  </div>
              <div className="overflow-hidden min-w-0">
                    <p className="font-sans font-black text-[#0061FF] text-base sm:text-lg tracking-tight leading-none uppercase truncate">Dataweaver</p>
                  </div>
                </div>
            </div>
            <div className="flex items-center justify-end shrink-0">
              {currentView === 'pacientes' ? (
                <button
                  type="button"
                  onClick={downloadCurrentDbf}
                  disabled={isDownloading}
                  aria-label="Download do DBF"
                  title="Download DBF"
                  className={cn(
                    "w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-[#0061FF] text-white shadow-sm shrink-0 flex items-center justify-center border border-white/10 transition-all",
                    isDownloading ? "opacity-60 cursor-not-allowed" : "hover:bg-blue-700 hover:-translate-y-0.5"
                  )}
                >
                  <span className={cn("material-symbols-outlined text-[20px]", isDownloading ? "animate-spin" : "")}>
                    {isDownloading ? 'progress_activity' : 'download'}
                  </span>
                </button>
              ) : (
                <div className="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-[#0061FF] flex items-center justify-center text-white shadow-sm shrink-0 font-bold text-[13px] sm:text-[14px]">
                  CA
                </div>
              )}
            </div>
          </header>

          {/* Page Container */}
          <main className="flex-1 px-4 sm:px-8 pb-6 sm:pb-10 overflow-y-auto custom-scrollbar flex flex-col items-center">
            <div className={cn("w-full flex flex-col items-center", currentView === 'pacientes' ? "max-w-[1600px]" : "max-w-4xl")}>
              {currentView === 'conciliacao' && (
                showSuccessScreen ? (
                  <div className="flex-1 flex flex-col items-center justify-center py-4 animate-fade-in w-full max-w-4xl mx-auto min-h-[60vh]">
                    <div className="flex flex-col items-center w-full text-center">
                      
                      {/* Success Icon Badge - More Compact */}
                      <div className="relative mb-6 scale-75 sm:scale-90">
                        <div className="w-40 h-40 bg-white rounded-full flex items-center justify-center shadow-xl shadow-zinc-100 border border-zinc-50">
                          <div className="w-32 h-32 bg-emerald-50 rounded-full flex items-center justify-center border border-emerald-100/50">
                            <span className="material-symbols-outlined text-[60px] text-emerald-500 font-light select-none" style={{ fontVariationSettings: "'FILL' 0, 'wght' 200, 'GRAD' 0, 'opsz' 48" }}>
                              new_releases
                            </span>
                          </div>
                        </div>
                        <div className="absolute -bottom-1 -right-1 w-12 h-12 bg-emerald-500 rounded-full border-[4px] border-[#fff] flex items-center justify-center shadow-md">
                          <span className="material-symbols-outlined text-white text-xl font-bold">check</span>
                        </div>
                      </div>

                      {/* Typography - More Compact */}
                      <h1 className="text-4xl font-black italic tracking-tighter text-[#1a1a1a] mb-3 uppercase">
                        SINCRONISMO <span className="text-emerald-500">CONCLUÍDO!</span>
                      </h1>
                      
                      <p className="text-lg text-zinc-500 font-medium max-w-lg mx-auto mb-8 leading-relaxed">
                        Seu banco de dados foi atualizado com sucesso.
                      </p>

                      {/* Buttons - More Compact */}
                      <div className="flex flex-col sm:flex-row gap-4 w-full max-w-xl px-4">
                        <button
                          type="button"
                          onClick={downloadCurrentDbf}
                          disabled={isDownloading}
                          className="flex-[1.2] py-5 bg-blue-600 text-white font-black rounded-xl shadow-lg shadow-blue-500/20 hover:bg-blue-700 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-3 text-lg uppercase tracking-tight disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                          <span className="material-symbols-outlined text-2xl">download</span>
                          {isDownloading ? 'BAIXANDO...' : 'BAIXAR BASE'}
                        </button>
                        
                        <button 
                          onClick={() => {
                            setShowSuccessScreen(false);
                            setDownloadLink(null);
                            setPreviewData(null);
                            setPatients([]);
                            setIsProcessing(false);
                            setStatus(null);
                            setProgress(0);
                          }}
                          className="flex-1 py-5 bg-white text-zinc-500 font-black rounded-xl shadow-sm hover:shadow-md hover:bg-zinc-50 transition-all transform hover:-translate-y-1 border border-zinc-200 text-base uppercase tracking-tight"
                        >
                          Nova Importação
                        </button>
                      </div>
                    </div>
                  </div>
                ) : (
                  <section className="animate-fade-in w-full flex flex-col items-center pt-8">
                      {!isPreviewing && !isProcessing && !downloadLink && (
                        <div className="flex flex-col items-center justify-center mb-6 text-center">
                          <h1 className="text-[30px] font-sans font-black tracking-tight text-[#1a1a1a]">
                            {previewData ? 'Pré-visualização da Importação' : 'Dataweaver'}
                          </h1>
                          {!previewData && <p className="text-zinc-400 mt-1 font-medium text-sm">Inicie o processamento enviando seus arquivos.</p>}
                        </div>
                      )}

                      <div className="w-full relative flex flex-col">
                        <div className="w-full flex items-center justify-center gap-4 absolute -top-12 right-0">
                        </div>
                      </div>

                      {/* ── UPLOAD ZONE ── */}
                      {!isPreviewing && !previewData && !isProcessing && !downloadLink && (
                        <div className="w-full flex justify-center mt-4 mb-4">
                          <div className="w-full flex flex-col items-center">
                            <div
                              onDragOver={handleDragOver}
                              onDragLeave={handleDragLeave}
                              onDrop={handleDrop}
                              className={cn(
                                "w-full border-2 border-dashed rounded-[16px] min-h-[240px] sm:min-h-[280px] flex flex-col items-center justify-center text-center px-5 sm:px-6 py-6 transition-all duration-300 cursor-pointer group relative overflow-hidden bg-transparent mb-4",
                                isDragging ? "border-blue-400 bg-blue-50/50 scale-[1.01]" : "border-zinc-300 hover:border-zinc-400 hover:bg-black/5"
                              )}
                            >
                              <label htmlFor="fileUpload" className="absolute inset-0 cursor-pointer z-10"></label>
                              <div className="w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center mb-4 text-[#0061FF] group-hover:scale-105 transition-all shadow-sm border border-[#0061FF]/10">
                                <div className="w-8 h-8 rounded-full bg-[#0061FF] flex items-center justify-center text-white shadow-md">
                                  <span className="material-symbols-outlined text-[18px]">cloud_upload</span>
                                </div>
                              </div>
                              <p className="text-[18px] sm:text-[20px] font-black text-[#1a1a1a] mb-1 tracking-tight">Arraste e solte o arquivo do medkey aqui</p>
                              <p className="text-[13px] sm:text-[14px] text-zinc-500 font-medium mb-6">Suporta arquivo csv, até 15MB cada</p>
                              <div className="px-5 py-2.5 border border-zinc-200 bg-white text-[#0061FF] font-bold rounded-md text-[13px] shadow-sm group-hover:border-[#0061FF]/30 transition-all z-10 relative pointer-events-none">
                                Ou carregue de seu computador
                              </div>
                              <input type="file" id="fileUpload" key={fileInputKey} className="hidden" onChange={handleFileUpload} />
                            </div>

                            <button
                              type="button"
                              onClick={downloadCurrentDbf}
                              disabled={isDownloading}
                              className="w-full sm:w-auto bg-[#0061FF] hover:bg-blue-700 text-white shadow-xl shadow-blue-500/20 rounded-md flex items-center justify-start pr-6 sm:pr-10 pl-2 py-2.5 gap-4 transition-all hover:-translate-y-1 z-10 relative disabled:opacity-60 disabled:cursor-not-allowed"
                            >
                              <div className="w-10 h-10 bg-white/20 rounded-md flex items-center justify-center shrink-0">
                                <span className="material-symbols-outlined text-white text-[20px] font-bold">download</span>
                              </div>
                              <div className="flex flex-col text-left justify-center pb-0.5">
                                <p className="text-[16px] font-bold leading-tight tracking-tight">Download DBF</p>
                              </div>
                            </button>
                          </div>
                        </div>
                      )}

                      {/* ── LOADING PREVIEW ── */}
                      {isPreviewing && (
                        <div className="w-full flex flex-col items-center justify-center py-12 animate-in fade-in zoom-in-95 duration-500">
                          <div className="relative w-32 h-32 flex items-center justify-center mb-10">
                            {/* Outer Glow Ring */}
                            <div className="absolute inset-0 rounded-full bg-blue-500/10 animate-ping"></div>
                            
                            {/* Main Spinner */}
                            <div className="absolute inset-0 rounded-full border-[3px] border-zinc-200 border-t-blue-500 animate-spin"></div>
                            
                            {/* Inner Pulsing Circle */}
                            <div className="w-20 h-20 rounded-full bg-white border border-black/5 shadow-2xl flex items-center justify-center relative z-10 animate-pulse">
                              <span className="material-symbols-outlined text-4xl text-blue-500 font-bold">query_stats</span>
                            </div>
                          </div>
                          
                          <div className="text-center space-y-3 z-10 transition-all">
                             <h3 className="text-3xl font-sans font-black text-zinc-900 tracking-tighter uppercase italic">
                               Analisando <span className="text-blue-500">arquivo...</span>
                             </h3>
                             <p className="text-zinc-500 font-bold text-sm bg-white/80 border border-black/5 px-4 py-1.5 rounded-full inline-flex items-center gap-2 shadow-sm">
                                <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                Comparando com o DBF em tempo real
                             </p>
                          </div>
                          
                          {/* Progress indicators - cosmetic */}
                          <div className="mt-12 flex gap-1.5">
                             {[...Array(5)].map((_, i) => (
                               <div 
                                 key={i} 
                                 className="w-1.5 h-1.5 rounded-full bg-blue-500/20"
                                 style={{ animation: `pulse 1.5s infinite ${i * 0.2}s` }}
                               ></div>
                             ))}
                          </div>
                        </div>
                      )}

                      {/* ── PREVIEW RECONCILIATION SCREEN ── */}
                      {previewData && !isProcessing && !downloadLink && (() => {
                        const hasNew = previewData.new.length > 0;
                        const hasUpdates = previewData.updates && previewData.updates.length > 0;
                        const hasWarnings = previewData.warnings.length > 0;
                        const hasEncodingIssues = (previewData.encoding?.issues_total || 0) > 0;
                        const encodingExamples = previewData.encoding?.examples || [];
                        return (
                          <div className="flex flex-col gap-3 flex-1 min-h-0">
                            {hasEncodingIssues && (
                              <div className="bg-amber-50 border border-amber-200 rounded-md shadow-sm p-4">
                                <div className="flex items-start gap-3">
                                  <div className="w-10 h-10 rounded-md bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                                    <span className="material-symbols-outlined text-xl">text_fields</span>
                                  </div>
                                  <div className="flex-1 min-w-0">
                                    <p className="text-sm font-black text-amber-800 uppercase tracking-wide">Encoding detectado</p>
                                    <p className="text-sm text-amber-700 mt-1 font-medium">
                                      Encontramos {previewData.encoding.rows_affected} linhas com {previewData.encoding.issues_total} ajustes de texto.
                                      O sistema vai normalizar para UTF-8, corrigir espaços em iniciais quando for seguro e registrar cada alteração no log.
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                      {Object.entries(previewData.encoding.fields || {}).map(([field, count]) => (
                                        <span key={field} className="px-2 py-1 rounded-full bg-white border border-amber-200 text-[10px] font-black text-amber-700 uppercase tracking-wide">
                                          {field}: {count}
                                        </span>
                                      ))}
                                    </div>
                                    {encodingExamples.length > 0 && (
                                      <div className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                                        {encodingExamples.slice(0, 3).map((example, i) => (
                                          <div key={i} className="bg-white/80 border border-amber-100 rounded-md p-2">
                                            <p className="text-[10px] font-black text-amber-700 uppercase tracking-wide">
                                              Linha {example.row} · {example.field}
                                            </p>
                                            <p className="text-[11px] text-zinc-500 line-through truncate" title={example.original}>
                                              {example.original}
                                            </p>
                                            <p className="text-[11px] font-black text-amber-800 truncate" title={example.corrected}>
                                              {example.corrected}
                                            </p>
                                          </div>
                                        ))}
                                      </div>
                                    )}
                                  </div>
                                </div>
                              </div>
                            )}

                            {/* Summary bar */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 shrink-0">
                              <div className="bg-white rounded-md border border-black/5 shadow-sm p-3 flex items-center gap-3">
                                <div className="w-10 h-10 rounded-md bg-green-50 text-green-600 flex items-center justify-center shrink-0">
                                  <span className="material-symbols-outlined text-xl">person_add</span>
                                </div>
                                <div>
                                  <p className="text-[10px] uppercase font-black text-zinc-400 tracking-widest">Novos</p>
                                  <p className="text-2xl font-black text-green-600">{previewData.new.length}</p>
                                </div>
                              </div>
                              <div className="bg-white rounded-md border border-black/5 shadow-sm p-3 flex items-center gap-3">
                                <div className="w-10 h-10 rounded-md bg-blue-50 text-[#0061FF] flex items-center justify-center shrink-0">
                                  <span className="material-symbols-outlined text-xl">magic_button</span>
                                </div>
                                <div>
                                  <p className="text-[10px] uppercase font-black text-zinc-400 tracking-widest">Atualizar</p>
                                  <p className="text-2xl font-black text-[#0061FF]">{previewData.updates?.length || 0}</p>
                                </div>
                              </div>
                              <div className="bg-white rounded-md border border-black/5 shadow-sm p-3 flex items-center gap-3">
                                <div className="w-10 h-10 rounded-md bg-zinc-100 text-zinc-500 flex items-center justify-center shrink-0">
                                  <span className="material-symbols-outlined text-xl">library_add_check</span>
                                </div>
                                <div>
                                  <p className="text-[10px] uppercase font-black text-zinc-400 tracking-widest">Já existem</p>
                                  <p className="text-2xl font-black text-zinc-500">{previewData.existing.length}</p>
                                </div>
                              </div>
                              <div className="bg-white rounded-md border border-black/5 shadow-sm p-3 flex items-center gap-3">
                                <div className="w-10 h-10 rounded-md bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                                  <span className="material-symbols-outlined text-xl">warning</span>
                                </div>
                                <div>
                                  <p className="text-[10px] uppercase font-black text-zinc-400 tracking-widest">Atenção</p>
                                  <p className="text-2xl font-black text-amber-500">{previewData.warnings.length}</p>
                                </div>
                              </div>
                            </div>

                            {/* Patient lists */}
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-3 min-h-[220px] md:h-[clamp(240px,30vh,320px)] shrink-0">

                              {/* NOVOS */}
                              <div className="bg-white rounded-md border border-green-100 shadow-sm flex flex-col overflow-hidden">
                                <div className="px-5 py-3.5 border-b border-green-50 flex items-center gap-2">
                                  <span className="w-2 h-2 rounded-full bg-green-500"></span>
                                  <h3 className="text-sm font-black text-zinc-800 uppercase tracking-wide">Serão Adicionados</h3>
                                </div>
                                <div className="flex-1 min-h-0 overflow-y-auto p-3 space-y-2 custom-scrollbar">
                                  {previewData.new.length === 0 ? (
                                    <p className="text-xs text-zinc-400 text-center py-6 font-medium">Nenhum paciente novo</p>
                                  ) : previewData.new.map((p, i) => (
                                    <div key={i} className="px-3 py-2.5 bg-green-50/50 border border-green-100 rounded-md">
                                      <p className="text-[11px] font-black text-zinc-800 leading-tight truncate flex items-baseline gap-1" title={p.nome_original}>
                                        <span className="text-[9px] font-mono text-zinc-400 w-4 shrink-0">{i + 1}.</span>
                                        {p.nome_original}
                                      </p>
                                      <p className="text-[9px] text-zinc-400 mt-0.5 font-medium">{p.data_cadastro}</p>
                                    </div>
                                  ))}
                                </div>
                              </div>

                              {/* ATUALIZAÇÕES */}
                              <div className="bg-white rounded-md border border-blue-100 shadow-sm flex flex-col overflow-hidden">
                                <div className="px-5 py-3.5 border-b border-blue-50 flex items-center gap-2">
                                  <span className="w-2 h-2 rounded-full bg-blue-500"></span>
                                  <h3 className="text-sm font-black text-zinc-800 uppercase tracking-wide">Preencher dados</h3>
                                </div>
                                <div className="flex-1 min-h-0 overflow-y-auto p-3 space-y-2 custom-scrollbar">
                                  {(!previewData.updates || previewData.updates.length === 0) ? (
                                    <p className="text-xs text-zinc-400 text-center py-6 font-medium">Nada a atualizar</p>
                                  ) : previewData.updates.map((p, i) => (
                                    <div key={i} className="px-3 py-2.5 bg-blue-50/50 border border-blue-100 rounded-md">
                                      <p className="text-[11px] font-black text-zinc-800 leading-tight truncate flex items-baseline gap-1" title={p.nome_original}>
                                        <span className="text-[9px] font-mono text-zinc-400 w-4 shrink-0">{i + 1}.</span>
                                        {p.nome_original}
                                      </p>
                                      <div className="flex flex-wrap gap-1 mt-1.5">
                                        {Object.entries(p.diff || {}).map(([key, _d]) => (
                                          <span key={key} className="text-[8px] font-black px-1.5 py-0.5 bg-[#0061FF] text-white rounded uppercase">
                                            +{key == 7 ? 'CPF' : key == 2 ? 'Resp' : key == 6 ? 'CEP' : 'Loc'}
                                          </span>
                                        ))}
                                      </div>
                                    </div>
                                  ))}
                                </div>
                              </div>

                              {/* JÁ EXISTEM */}
                              <div className="bg-white rounded-md border border-zinc-100 shadow-sm flex flex-col overflow-hidden">
                                <div className="px-5 py-3.5 border-b border-zinc-50 flex items-center gap-2">
                                  <span className="w-2 h-2 rounded-full bg-zinc-400"></span>
                                  <h3 className="text-sm font-black text-zinc-800 uppercase tracking-wide">Já na Base</h3>
                                </div>
                                <div className="flex-1 min-h-0 overflow-y-auto p-3 space-y-2 custom-scrollbar">
                                  {previewData.existing.length === 0 ? (
                                    <p className="text-xs text-zinc-400 text-center py-6 font-medium">Nenhum duplicado</p>
                                  ) : previewData.existing.map((p, i) => (
                                    <div key={i} className="px-3 py-2.5 bg-zinc-50 border border-zinc-100 rounded-md">
                                      <p className="text-[11px] font-black text-zinc-500 leading-tight truncate flex items-baseline gap-1" title={p.nome_original}>
                                        <span className="text-[9px] font-mono text-zinc-300 w-4 shrink-0">{i + 1}.</span>
                                        {p.nome_original}
                                      </p>
                                      <p className="text-[9px] text-zinc-400 mt-0.5 font-medium">{p.data_cadastro}</p>
                                    </div>
                                  ))}
                                </div>
                              </div>

                              {/* ATENÇÃO */}
                              <div className="bg-white rounded-md border border-amber-100 shadow-sm flex flex-col overflow-hidden">
                                <div className="px-5 py-3.5 border-b border-amber-50 flex items-center gap-2">
                                  <span className="w-2 h-2 rounded-full bg-amber-400"></span>
                                  <h3 className="text-sm font-black text-zinc-800 uppercase tracking-wide">Verificar</h3>
                                </div>
                                <div className="flex-1 min-h-0 overflow-y-auto p-3 space-y-2 custom-scrollbar">
                                  {previewData.warnings.length === 0 ? (
                                    <p className="text-xs text-zinc-400 text-center py-6 font-medium">Nenhum item de atenção</p>
                                  ) : previewData.warnings.map((p, i) => (
                                      <div key={i} className="px-3 py-2.5 bg-amber-50/50 border border-amber-100 rounded-md">
                                        <p className="text-[10px] font-bold text-zinc-400 line-through leading-tight opacity-50 mb-1 flex items-baseline gap-1" title={p.nome_original}>
                                          <span className="text-[9px] font-mono text-zinc-300 w-4 shrink-0">{i + 1}.</span>
                                          {p.nome_original}
                                        </p>
                                        <div className="flex items-center gap-1.5">
                                          <span className="material-symbols-outlined text-[12px] text-amber-500">subdirectory_arrow_right</span>
                                          <p className="text-[11px] font-black text-amber-700 leading-tight">
                                            {p.nome}
                                          </p>
                                        </div>
                                        <p className="text-[9px] text-amber-600/70 mt-1 font-bold leading-tight uppercase tracking-tighter italic bg-white/50 inline-block px-1 rounded">{p.reason}</p>
                                      </div>
                                  ))}
                                </div>
                              </div>

                            </div>

                            {/* Action bar */}
                            <div className="bg-white rounded-md border border-black/5 shadow-sm p-3 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 shrink-0">
                              <div>
                                <p className="text-[12px] sm:text-[13px] font-semibold text-zinc-700 leading-tight">
                                  {(hasNew || hasWarnings || hasUpdates)
                                    ? 'A base será atualizada com novos registros e informações complementares'
                                    : 'Todos os registros já existem na base e estão completos — nada será alterado'}
                                </p>
                                <p className="text-[10px] text-zinc-400 font-medium mt-0.5">Arquivo: {previewData.file}</p>
                              </div>
                              <div className="flex flex-col sm:flex-row gap-3 w-full lg:w-auto shrink-0">
                                <Button
                                  variant="outline"
                                  onClick={cancelPreview}
                                  className="rounded-md px-6 py-4 font-bold border-black/10 hover:bg-zinc-50 w-full sm:w-auto"
                                >
                                  Cancelar
                                </Button>
                                <Button
                                  onClick={confirmImport}
                                  disabled={!hasNew && !hasWarnings && !hasUpdates}
                                  className="rounded-md px-8 py-4 font-black bg-gradient-to-r from-blue-400 to-blue-500 hover:from-blue-500 hover:to-[#003c99] text-white shadow-[0_6px_20px_rgba(249,115,22,0.3)] transition-all hover:-translate-y-0.5 border-0 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:translate-y-0 w-full sm:w-auto"
                                >
                                  <span className="material-symbols-outlined mr-2 text-[18px]">check_circle</span>
                                  Confirmar Importação
                                </Button>
                              </div>
                            </div>
                          </div>
                        );
                      })()}

                       {/* ── PROCESSING ── */}
                       {isProcessing && (
                         <div className="w-full flex flex-col items-center justify-center py-12 animate-in fade-in zoom-in-95 duration-500">
                           <div className="relative w-32 h-32 flex items-center justify-center mb-10">
                             <div className="absolute inset-0 rounded-full bg-blue-500/10 animate-ping"></div>
                             <div className="absolute inset-0 rounded-full border-[3px] border-zinc-200 border-t-blue-500 animate-spin"></div>
                             <div className="w-20 h-20 rounded-full bg-white border border-black/5 shadow-2xl flex items-center justify-center relative z-10 animate-pulse">
                               <span className="material-symbols-outlined text-4xl text-blue-500 font-bold">sync</span>
                             </div>
                           </div>
                           
                           <div className="text-center space-y-3 z-10 transition-all max-w-lg w-full">
                              <h3 className="text-2xl font-sans font-black text-zinc-900 tracking-tighter uppercase italic">
                                {status.replace('...', '')} <span className="text-blue-500">Registros...</span>
                              </h3>
                              <p className="text-zinc-500 font-bold text-sm bg-white/80 border border-black/5 px-4 py-1.5 rounded-full inline-flex items-center gap-2 shadow-sm mb-8">
                                 <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                 Operação em curso. Por favor, não feche esta janela.
                              </p>

                              <div className="w-full space-y-4 pt-4">
                                <div className="w-full h-4 bg-zinc-200/50 rounded-full overflow-hidden p-1 border border-black/5 shadow-inner">
                                  <div 
                                    className="h-full bg-gradient-to-r from-blue-400 to-blue-600 rounded-full transition-all duration-500 relative overflow-hidden" 
                                    style={{ width: `${progress}%` }}
                                  >
                                    <div className="absolute inset-0 bg-white/20 animate-shimmer skew-x-[-20deg]"></div>
                                  </div>
                                </div>
                                <div className="flex justify-between items-center px-1">
                                  <p className="text-[10px] font-black text-blue-600 tracking-[0.2em] uppercase">{progress}% COMPLETO</p>
                                  <div className="flex items-center gap-1.5">
                                    <span className="w-1 h-1 rounded-full bg-green-500 animate-pulse"></span>
                                    <p className="text-[10px] text-zinc-400 font-black uppercase tracking-widest">MODO TURBO ATIVO</p>
                                  </div>
                                </div>
                              </div>
                           </div>
                         </div>
                       )}

                       {/* ── SUCCESS ── */}
                       {!isProcessing && downloadLink && (
                         <div className="w-full flex flex-col items-center justify-center py-24 animate-in fade-in zoom-in-95 duration-700">
                           <div className="w-32 h-32 bg-green-50/50 text-green-500 rounded-full border-4 border-white flex items-center justify-center mb-10 shadow-2xl relative">
                             <div className="absolute inset-0 rounded-full bg-green-500/10 animate-ping"></div>
                             <span className="material-symbols-outlined text-5xl font-bold relative z-10">verified</span>
                           </div>
                           
                           <div className="text-center space-y-4 mb-12">
                             <h2 className="text-4xl font-sans font-black text-zinc-900 tracking-tighter uppercase italic">
                               Sincronismo <span className="text-green-500">Concluído!</span>
                             </h2>
                             <p className="text-zinc-500 text-lg font-medium max-w-md mx-auto leading-relaxed">
                               Seu banco de dados foi atualizado com sucesso seguindo todos os protocolos de segurança.
                             </p>
                           </div>

                           <div className="flex flex-col sm:flex-row gap-4 w-full max-w-lg">
                           <Button
                             type="button"
                             onClick={downloadCurrentDbf}
                             disabled={isDownloading}
                             className="flex-[2] h-16 bg-[#0061FF] hover:bg-blue-700 text-white font-black rounded-md shadow-2xl shadow-blue-500/30 transition-all hover:-translate-y-1 disabled:opacity-60 disabled:cursor-not-allowed"
                           >
                             <span className="material-symbols-outlined font-bold">download</span>
                             {isDownloading ? 'BAIXANDO...' : 'BAIXAR BASE ATUALIZADA'}
                           </Button>
                             <Button 
                               variant="outline" 
                               onClick={() => { setDownloadLink(null); setPreviouslyImported(null); setPreviewData(null); }} 
                               className="flex-1 h-16 border-zinc-200 text-zinc-600 font-bold hover:bg-zinc-50 transition-all rounded-md"
                             >
                               Nova Importação
                             </Button>
                           </div>
                         </div>
                       )}

                  </section>
                )
              )}

            {currentView === 'pacientes' && (
              <section className="space-y-6 animate-fade-in flex-1 flex flex-col w-full pb-10 min-h-0">
                <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 shrink-0 px-2">
                  <div>
                    <h2 className="text-[32px] font-sans font-black text-zinc-900 tracking-tight leading-none mb-1">DBF</h2>
                    <p className="inline-flex items-center gap-2 bg-white/80 border border-black/5 shadow-sm px-3 py-1.5 rounded-full text-zinc-600 font-bold text-[12px] sm:text-sm whitespace-nowrap">
                      <span className="w-1.5 h-1.5 rounded-full bg-[#0061FF] animate-pulse"></span>
                      <span className="text-zinc-900 font-black">{totalRecords}</span>
                      <span className="text-zinc-500 font-bold">registros</span>
                      <span className="text-zinc-300 font-black">•</span>
                      <span className="text-zinc-900 font-black">{currentPage}/{totalPages}</span>
                      <span className="text-zinc-500 font-bold">página</span>
                      <span className="text-zinc-300 font-black">•</span>
                      <span className="text-zinc-600 font-bold">Clique no paciente para ver detalhes</span>
                    </p>
                  </div>
                  <div className="flex flex-wrap items-center gap-3">
                    <div className="flex items-center gap-2">
                      <div className="relative">
                        <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-sm">search</span>
                        <input 
                          type="text" 
                          placeholder="Pesquisar registros..." 
                          className="pl-10 pr-4 py-2 bg-zinc-200/50 rounded-lg border-none text-sm w-72 focus:ring-2 focus:ring-[#0061FF]/20 transition-all font-medium placeholder:text-zinc-500" 
                          value={dbfSearch} 
                          onChange={(e) => {
                            setCurrentPage(1);
                            setDbfSearch(e.target.value);
                          }} 
                        />
                      </div>
                      
                      {sortConfig.key !== 'id' && (
                        <button 
                          onClick={() => setSortConfig({ key: 'id', direction: 'desc' })}
                          className="flex items-center gap-1.5 px-3 py-2 bg-white border border-black/5 hover:bg-zinc-50 text-zinc-500 rounded-lg text-xs font-bold transition-all animate-in fade-in slide-in-from-right-2"
                          title="Limpar filtros e voltar para ordem cronológica"
                        >
                          <span className="material-symbols-outlined text-[16px]">restart_alt</span>
                          Limpar Ordenação
                        </button>
                      )}
                    </div>
                    <select 
                      className="bg-zinc-200/50 border-none rounded-lg text-sm py-2 px-4 focus:ring-2 focus:ring-[#0061FF]/20 font-bold text-zinc-600 outline-none" 
                      value={pageSize} 
                      onChange={(e) => {
                        setCurrentPage(1);
                        setPageSize(Number(e.target.value));
                      }}
                    >
                      <option value={20}>20</option>
                      <option value={50}>50</option>
                      <option value={100}>100</option>
                    </select>
                    <div className="inline-flex p-1 bg-white rounded-lg border border-black/5 shadow-sm">
                      <button
                        type="button"
                        onClick={() => setDbfViewMode('compact')}
                        className={cn(
                          "px-3 py-2 rounded-md text-[11px] font-black uppercase tracking-widest transition-all",
                          dbfViewMode === 'compact' ? "bg-[#0061FF] text-white shadow-sm" : "text-zinc-500 hover:text-zinc-700 hover:bg-zinc-50"
                        )}
                      >
                        Compacta
                      </button>
                      <button
                        type="button"
                        onClick={() => setDbfViewMode('complete')}
                        className={cn(
                          "px-3 py-2 rounded-md text-[11px] font-black uppercase tracking-widest transition-all",
                          dbfViewMode === 'complete' ? "bg-[#0061FF] text-white shadow-sm" : "text-zinc-500 hover:text-zinc-700 hover:bg-zinc-50"
                        )}
                      >
                        Campos completos
                      </button>
                    </div>
                  </div>
                </div>

                <div className="flex flex-col gap-3 rounded-2xl border border-black/5 bg-white px-4 py-3 shadow-sm lg:flex-row lg:items-center lg:justify-between shrink-0">
                  <div className="flex flex-wrap items-center gap-1.5">
                    <button
                      disabled={currentPage === 1}
                      onClick={() => setCurrentPage(1)}
                      className="w-10 h-10 flex items-center justify-center rounded-lg border border-black/5 bg-white text-zinc-400 hover:bg-zinc-50 disabled:opacity-30 disabled:hover:bg-white"
                    >
                      <span className="material-symbols-outlined text-[18px]">first_page</span>
                    </button>
                    <button
                      disabled={currentPage === 1}
                      onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                      className="w-10 h-10 flex items-center justify-center rounded-lg border border-black/5 bg-white text-zinc-400 hover:bg-zinc-50 disabled:opacity-30"
                    >
                      <span className="material-symbols-outlined text-[18px]">chevron_left</span>
                    </button>

                    <div className="flex items-center gap-1">
                      {(() => {
                        const pages = [];
                        const delta = 1;

                        let start = Math.max(1, currentPage - delta);
                        let end = Math.min(totalPages, currentPage + delta);

                        if (currentPage <= delta) {
                          end = Math.min(totalPages, 3);
                        }
                        if (currentPage > totalPages - delta) {
                          start = Math.max(1, totalPages - 2);
                        }

                        if (start > 1) {
                          pages.push(
                            <button key={1} onClick={() => setCurrentPage(1)} className="w-10 h-10 rounded-lg flex items-center justify-center text-[13px] font-black transition-all bg-white border border-black/5 text-zinc-500 hover:bg-zinc-50">1</button>
                          );
                          if (start > 2) pages.push(<span key="start-dots" className="px-1 text-zinc-300 font-bold">...</span>);
                        }

                        for (let i = start; i <= end; i++) {
                          pages.push(
                            <button
                              key={i}
                              onClick={() => setCurrentPage(i)}
                              className={cn(
                                "w-10 h-10 rounded-lg flex items-center justify-center text-[13px] font-black transition-all",
                                currentPage === i
                                  ? "bg-[#0061FF] text-white shadow-lg shadow-[#0061FF]/20 z-10"
                                  : "bg-white border border-black/5 text-zinc-500 hover:bg-zinc-50"
                              )}
                            >
                              {i}
                            </button>
                          );
                        }

                        if (end < totalPages) {
                          if (end < totalPages - 1) pages.push(<span key="end-dots" className="px-1 text-zinc-300 font-bold">...</span>);
                          pages.push(
                            <button key={totalPages} onClick={() => setCurrentPage(totalPages)} className="w-10 h-10 rounded-lg flex items-center justify-center text-[13px] font-black transition-all bg-white border border-black/5 text-zinc-500 hover:bg-zinc-50">{totalPages}</button>
                          );
                        }

                        return pages;
                      })()}
                    </div>

                    <button
                      disabled={currentPage === totalPages}
                      onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                      className="w-10 h-10 flex items-center justify-center rounded-lg border border-black/5 bg-white text-zinc-400 hover:bg-zinc-50 disabled:opacity-30"
                    >
                      <span className="material-symbols-outlined text-[18px]">chevron_right</span>
                    </button>
                    <button
                      disabled={currentPage === totalPages}
                      onClick={() => setCurrentPage(totalPages)}
                      className="w-10 h-10 flex items-center justify-center rounded-lg border border-black/5 bg-white text-zinc-400 hover:bg-zinc-50 disabled:opacity-30"
                    >
                      <span className="material-symbols-outlined text-[18px]">last_page</span>
                    </button>

                    <div className="hidden lg:block h-10 border-l border-zinc-200 mx-2"></div>

                    <div className="flex items-center gap-2">
                      <p className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">Ir para</p>
                      <input
                        type="number"
                        min="1"
                        max={totalPages}
                        className="w-14 h-10 rounded-lg border border-black/5 text-center text-[13px] font-black text-zinc-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                        placeholder="1"
                        value={navigatorPage || ''}
                        onChange={(e) => setNavigatorPage(e.target.value)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') {
                            const pageNum = parseInt(navigatorPage);
                            if (!isNaN(pageNum) && pageNum >= 1 && pageNum <= totalPages) {
                              setCurrentPage(pageNum);
                            }
                          }
                        }}
                      />
                    </div>
                  </div>

                  <p className="text-[12px] font-bold text-zinc-400">
                    Exibindo {(currentPage - 1) * pageSize + 1}-{Math.min(currentPage * pageSize, totalRecords)} de {totalRecords}
                  </p>
                </div>

                {isLoadingDB ? (
                  <div className="w-full flex flex-col items-center justify-center py-24 animate-in fade-in duration-500">
                    <div className="relative w-24 h-24 flex items-center justify-center mb-6">
                      <div className="absolute inset-0 rounded-full border-[3px] border-zinc-200 border-t-[#0061FF] animate-spin"></div>
                      <div className="w-16 h-16 rounded-full bg-white shadow-xl flex items-center justify-center relative z-10 animate-pulse">
                        <span className="material-symbols-outlined text-3xl text-[#0061FF]">database</span>
                      </div>
                    </div>
                    <div className="text-center space-y-2">
                      <p className="text-zinc-900 font-black text-xl tracking-tight uppercase italic">Carregando <span className="text-[#0061FF]">DBF...</span></p>
                      <p className="text-zinc-400 font-bold text-xs uppercase tracking-widest">Sincronizando com a base master</p>
                    </div>
                  </div>
                ) : (
                  <div className="flex-1 flex flex-col w-full min-h-0">
                    <div className="bg-white rounded-[20px] shadow-[0_4px_40px_rgba(0,0,0,0.03)] border border-black/5 overflow-hidden flex flex-col min-h-0">
                      <div className="overflow-x-auto overflow-y-auto custom-scrollbar flex-1">
                        <table className={cn(
                          "w-full text-left border-collapse",
                          dbfViewMode === 'complete' ? "min-w-[1800px]" : "min-w-[1100px]"
                        )}>
                          <thead className="sticky top-0 bg-white z-10">
                            <tr className="border-b border-black/[0.03]">
                              {activeDbfColumns.map((column) => (
                                <th
                                  key={column.key}
                                  className={column.thClassName}
                                >
                                  {column.key === 'name' ? (
                                    <button
                                      type="button"
                                      onClick={() => handleSort('name')}
                                      className="flex items-center gap-1"
                                    >
                                      Nome do Paciente
                                      <span className="material-symbols-outlined text-[14px]">
                                        {sortConfig.key === 'name' ? (sortConfig.direction === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more'}
                                      </span>
                                    </button>
                                  ) : column.key === 'responsible' ? (
                                    <button
                                      type="button"
                                      onClick={() => handleSort('responsible')}
                                      className="flex items-center gap-1"
                                    >
                                      Responsável
                                      <span className="material-symbols-outlined text-[14px]">
                                        {sortConfig.key === 'responsible' ? (sortConfig.direction === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more'}
                                      </span>
                                    </button>
                                  ) : column.key === 'city_state' ? (
                                    <button
                                      type="button"
                                      onClick={() => handleSort('location')}
                                      className="flex items-center gap-1"
                                    >
                                      Cidade / UF Res.
                                      <span className="material-symbols-outlined text-[14px]">
                                        {sortConfig.key === 'location' ? (sortConfig.direction === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more'}
                                      </span>
                                    </button>
                                  ) : (
                                    column.header
                                  )}
                                </th>
                              ))}
                            </tr>
                          </thead>
                          <tbody>
                            {dbfRecords.map((record, i) => {
                              const rowNumber = (currentPage - 1) * pageSize + i + 1;

                              const avatarColors = [
                                'bg-blue-100/50 text-[#0061FF]',
                                'bg-purple-100/50 text-[#7c3aed]',
                                'bg-orange-100/50 text-[#f97316]',
                                'bg-indigo-100/50 text-[#4338ca]'
                              ];
                              const colorClass = avatarColors[i % avatarColors.length];
                              const openDetails = () => openDbfDrawer(record, rowNumber);

                              return (
                                <tr
                                  key={i}
                                  onClick={openDetails}
                                  className="group hover:bg-blue-50/30 transition-colors border-b border-black/[0.02] last:border-0 even:bg-zinc-100/70 cursor-pointer"
                                >
                                  {activeDbfColumns.map((column) => (
                                    <td key={column.key} className={column.tdClassName}>
                                      {column.render(record, rowNumber, i, colorClass, openDetails)}
                                    </td>
                                  ))}
                                </tr>
                              );
                            })}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                )}
              </section>
            )}



            {currentView === 'suporte' && (
              <section className="animate-fade-in max-w-4xl mx-auto py-4">
                <Card className="bg-white rounded-md border border-black/5 shadow-soft overflow-hidden">
                  <div className="grid grid-cols-1 md:grid-cols-2">
                    <div className="p-12 flex flex-col justify-center space-y-8">
                       <div className="space-y-4">
                          <div className="w-16 h-16 rounded-md bg-blue-100 text-[#0052cc] flex items-center justify-center font-black">
                            <span className="material-symbols-outlined text-3xl">support_agent</span>
                          </div>
                          <h1 className="text-4xl font-sans font-black text-zinc-900 tracking-tight leading-tight">Suporte Técnico Especializado</h1>
                          <p className="text-zinc-500 font-medium leading-relaxed">Clique no botão abaixo para nos enviar a mensagem: <strong className="text-zinc-800">"Olá, estou com dificuldades no sistema, poderia nos apoiar ?"</strong> através do WhatsApp. Nossa equipe responderá em breve.</p>
                       </div>

                       <Button 
                         onClick={() => window.open('https://wa.me/5511981748445?text=Ol%C3%A1%2C%20estou%20com%20dificuldades%20no%20sistema%2C%20poderia%20nos%20apoiar%20%3F', '_blank')}
                         className="w-full h-16 rounded-md bg-gradient-to-r from-blue-500 to-[#0052cc] text-white font-black hover:scale-[1.02] transition-all shadow-xl shadow-blue-500/20 text-lg flex items-center justify-center gap-3"
                       >
                         <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.414 0 .018 5.394 0 12.03c0 2.119.554 4.188 1.603 6l-1.706 6.23 6.377-1.674a11.845 11.845 0 005.726 1.474h.005c6.634 0 12.03-5.394 12.033-12.032a11.83 11.83 0 00-3.411-8.505"/>
                         </svg>
                         Falar com Desenvolvimento
                       </Button>

                       <div className="pt-8 border-t border-black/5">
                          <div className="flex items-center gap-3 text-zinc-400">
                             <span className="material-symbols-outlined text-sm">schedule</span>
                             <span className="text-[11px] font-black uppercase tracking-widest">Atendimento Prioritário - Dias Úteis</span>
                          </div>
                       </div>
                    </div>
                    
                    <div className="bg-zinc-50 flex items-center justify-center p-12 relative overflow-hidden group">
                       <div className="absolute inset-0 opacity-10 bg-[radial-gradient(#f97316_1px,transparent_1px)] [background-size:20px_20px] animate-pulse"></div>
                       <img 
                        src="img/support_illust.png" 
                        alt="Suporte Técnico" 
                        className="relative z-10 w-full max-w-sm drop-shadow-2xl group-hover:scale-105 transition-transform duration-700"
                       />
                    </div>
                  </div>
                </Card>
                </section>
              )}

            {currentView === 'logs' && (
              <section className="space-y-6 animate-fade-in">
                <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                  <div className="space-y-1">
                    <h2 className="text-2xl sm:text-3xl font-sans font-black text-zinc-900 tracking-tight">Histórico</h2>
                    <p className="text-zinc-500 font-medium text-sm">Importações processadas e seus resultados.</p>
                  </div>

	                  <div className="flex flex-col sm:flex-row gap-3 sm:items-center">
	                    <div className="relative">
	                      <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-[18px]">search</span>
	                      <input
	                        value={historyQuery}
	                        onChange={(e) => setHistoryQuery(e.target.value)}
	                        placeholder="Buscar arquivo..."
	                        className="w-full sm:w-[320px] pl-10 pr-4 py-2.5 bg-white border border-black/5 rounded-xl text-sm font-semibold text-zinc-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 shadow-sm"
	                      />
	                    </div>
	                  </div>
	                </div>
                
	                {importHistory.length > 0 ? (
	                  <div className="grid grid-cols-1 gap-2">
	                    {importHistory
	                      .filter((session) => {
	                        const q = historyQuery.trim().toLowerCase();
	                        if (!q) return true;
	                        const name = String(session.file_name || '').toLowerCase();
	                        return name.includes(q);
	                      })
	                      .map((session, i) => {
	                      const total = Number(session.total_read || session.total) || 0;
	                      const added = Number(session.added) || 0;
	                      const updated = Number(session.updated) || 0;
                      const ts = session.timestamp || session.ts;
                      const duplicates = session.duplicates !== undefined ? Number(session.duplicates) : Math.max(0, total - added - updated);
                      
                      let statusText = "Processado";
                      let statusColor = "text-green-700 bg-green-50 border-green-200";
                      
                      if (added > 0 && updated > 0) {
                        statusText = "Novos + Atualizados";
                        statusColor = "text-[#0052cc] bg-blue-50 border-blue-200";
                      } else if (added > 0) {
                        statusText = "Novos Registros";
                        statusColor = "text-green-700 bg-green-50 border-green-200";
                      } else if (updated > 0) {
                        statusText = "Apenas Atualizações";
                        statusColor = "text-blue-600 bg-blue-50 border-blue-100";
                      } else {
                        statusText = "Sem mudanças";
                        statusColor = "text-zinc-500 bg-zinc-50 border-zinc-200";
                      }

	                      return (
		                      <Card key={session.id || i} className="bg-white rounded-2xl overflow-hidden shadow-sm border border-black/5 hover:border-blue-200 transition-all">
		                        <CardContent className="p-0">
		                          <div className="p-2.5 sm:p-3 flex flex-col gap-1.5">
		                            <div className="flex items-start justify-between gap-3">
		                              <div className="min-w-0">
		                                <div className="flex items-center gap-2">
		                                  <span className={cn(
		                                    "w-2 h-2 rounded-full",
		                                    (added > 0 || updated > 0) ? "bg-blue-500" : "bg-zinc-300"
		                                  )}></span>
		                                  <p className="text-[10px] uppercase font-black text-zinc-400 tracking-widest">{ts}</p>
		                                </div>
		                                <h3 className="mt-1 text-[14px] sm:text-[15px] font-black text-zinc-900 tracking-tight truncate" title={session.file_name}>
		                                  {session.file_name}
		                                </h3>
		                              </div>

		                              <div className="flex flex-col items-end gap-1 shrink-0">
		                                <span className={cn("px-2.5 py-0.5 rounded-full text-[9px] font-black border uppercase tracking-tighter", statusColor)}>
		                                  {statusText}
		                                </span>
		                                <Button
		                                  type="button"
		                                  onClick={() => {
		                                    setSelectedHistoryItem(session);
		                                    setShowHistoryDetailModal(true);
		                                  }}
		                                  className="h-8 px-3 rounded-lg bg-[#0061FF] hover:bg-blue-700 text-white font-black shadow-sm text-[12px]"
		                                >
		                                  <span className="material-symbols-outlined mr-2 text-[16px]">list_alt</span>
		                                  Registros
		                                </Button>
		                              </div>
		                            </div>

			                            <div className="flex flex-wrap gap-1.5">
			                              <span className="px-2.5 py-0.5 rounded-full bg-zinc-50 border border-black/5 text-[9px] font-black text-zinc-600 uppercase tracking-widest">
			                                Total {total}
			                              </span>
			                              <span className="px-2.5 py-0.5 rounded-full bg-green-50 border border-green-200 text-[9px] font-black text-green-700 uppercase tracking-widest">
			                                Novos {added}
			                              </span>
			                              <span className="px-2.5 py-0.5 rounded-full bg-blue-50 border border-blue-200 text-[9px] font-black text-[#0052cc] uppercase tracking-widest">
			                                Atualizados {updated}
			                              </span>
			                              <span className="px-2.5 py-0.5 rounded-full bg-zinc-50 border border-zinc-200 text-[9px] font-black text-zinc-500 uppercase tracking-widest">
			                                Duplicados {duplicates}
			                              </span>
			                            </div>
			                          </div>
			                        </CardContent>
			                      </Card>
                      );
                    })}
                  </div>
                ) : (
                  <Card className="bg-zinc-50/50 rounded-md p-24 text-center border-2 border-dashed border-black/10">
                      <span className="material-symbols-outlined text-5xl text-zinc-300 mb-4 scale-150 block">history_toggle_off</span>
                      <p className="text-zinc-500 font-black text-xl tracking-tight mb-2">Nenhum histórico encontrado.</p>
                      <p className="text-zinc-400 text-sm font-medium mb-8">Suas importações aparecerão aqui após o processamento.</p>
                      <Button onClick={() => setCurrentView('conciliacao')} className="bg-white border border-black/10 shadow-sm rounded-md px-10 h-14 font-black text-blue-500 hover:bg-blue-50 transition-all">Iniciar agora</Button>
                  </Card>
                )}
              </section>
            )}
            </div>
          </main>

          {/* Standardized Footer */}
          <Footer />

        </div>
      </div>



      {showModal && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-zinc-900/60 backdrop-blur-md" onClick={() => setShowModal(false)}></div>
          <div className="relative bg-white rounded-4xl shadow-2xl w-full max-w-2xl overflow-hidden animate-fade-in border border-zinc-200">
            <div className="p-12 text-center">
              {patients.length > 0 ? (
                <>
                  <div className="w-20 h-20 bg-blue-100 text-[#0052cc] rounded-3xl flex items-center justify-center mx-auto mb-8 transform rotate-3 shadow-lg shadow-blue-500/10">
                    <span className="material-symbols-outlined text-4xl">person_add</span>
                  </div>
                  <h2 className="text-3xl font-black text-zinc-900 mb-2 leading-tight">Sincronismo Concluído</h2>
                  <p className="text-zinc-400 mb-8 font-medium">A base master foi atualizada com sucesso.</p>

                  <div className="grid grid-cols-3 gap-3 mb-8">
                    <div className="bg-zinc-50 p-4 rounded-md border border-zinc-100/50 flex flex-col items-center">
                      <p className="text-[9px] text-zinc-400 font-black uppercase tracking-widest mb-1">Novos</p>
                      <p className="text-2xl font-black text-zinc-900">{stats.divergences - (stats.updated || 0)}</p>
                    </div>
                    <div className="bg-blue-50 p-4 rounded-md border border-blue-100/50 flex flex-col items-center">
                      <p className="text-[9px] text-blue-500 font-black uppercase tracking-widest mb-1">Atualizados</p>
                      <p className="text-2xl font-black text-blue-600">{stats.updated || 0}</p>
                    </div>
                    <div className="bg-zinc-50 p-4 rounded-md border border-zinc-100/50 flex flex-col items-center opacity-50">
                      <p className="text-[9px] text-zinc-400 font-black uppercase tracking-widest mb-1">Total Lido</p>
                      <p className="text-2xl font-black text-zinc-900">{stats.totalRead || 0}</p>
                    </div>
                  </div>
                  
                  <p className="text-left text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                    Detalhamento dos Registros Processados
                  </p>
                  
                  <div className="space-y-3 max-h-[350px] overflow-y-auto pr-2 custom-scrollbar text-left">
                    {patients.map((p, i) => (
                      <div key={i} className="flex items-center justify-between p-4 bg-zinc-50 rounded-md border border-zinc-100 hover:border-blue-200 hover:bg-white transition-all group">
                        <div className="flex items-center gap-4">
                          <div className="text-[10px] font-mono text-zinc-300 w-5 text-right font-bold">
                            {i + 1}.
                          </div>
                          <div className={`w-10 h-10 rounded-md flex items-center justify-center font-bold text-xs ${p.color} shadow-sm`}>
                            {p.initials}
                          </div>
                          <div>
                            <div className="flex items-center gap-2">
                              <p className="font-bold text-zinc-900 text-sm group-hover:text-[#0052cc] transition-colors uppercase">{p.nome}</p>
                              {p.truncated && (
                                <span className="flex items-center text-[9px] font-black text-amber-500 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 uppercase tracking-tighter">
                                  Truncado
                                </span>
                              )}
                            </div>
                            <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-tighter">
                              {p.type === 'update' ? 'Enriquecimento de Dados' : 'Sincronizado via Hub'}
                            </p>
                          </div>
                        </div>
                        <span className="px-3 py-1 bg-white border border-zinc-200 rounded-lg text-[10px] text-zinc-500 font-bold">ID: {p.id}</span>
                      </div>
                    ))}
                  </div>
                </>
              ) : (
                <div className="py-12">
                   <div className="w-24 h-24 bg-blue-50 text-blue-500 rounded-4xl flex items-center justify-center mx-auto mb-8 shadow-inner"><span className="material-symbols-outlined text-5xl">check_circle</span></div>
                   <h2 className="text-3xl font-bold text-zinc-900">Base Sincronizada</h2>
                   <p className="text-zinc-500 mt-4 text-lg">Nenhum paciente novo precisou ser adicionado à base master.</p>
                </div>
              )}
              <div className="mt-12 flex flex-col sm:flex-row gap-4">
                <button
                  type="button"
                  onClick={async () => {
                    await downloadCurrentDbf();
                    setTimeout(() => {
                      setShowModal(false);
                      setDownloadLink(null);
                      setPatients([]);
                    }, 500);
                  }}
                  className="flex-1 py-7 bg-blue-600 text-white font-black rounded-md shadow-2xl shadow-blue-500/20 hover:bg-blue-700 transition-all transform hover:-translate-y-1 text-center flex items-center justify-center gap-2"
                >
                  <span className="material-symbols-outlined">download</span>
                  FAZER DOWNLOAD AGORA!
                </button>
                <Button 
                  onClick={() => { 
                    setShowModal(false); 
                    setDownloadLink(null);
                    setPreviewData(null);
                    setPatients([]);
                  }} 
                  className="px-8 py-7 bg-zinc-100 text-zinc-500 font-bold rounded-md hover:bg-zinc-200 transition-all border-0 shadow-none uppercase tracking-tighter"
                >
                  Download mais tarde
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
	      {errorModal && (
	        <div className="fixed inset-0 z-[130] flex items-center justify-center p-4">
	          <div className="absolute inset-0 bg-zinc-900/60 backdrop-blur-md" onClick={() => setErrorModal(null)}></div>
	          <div className="relative bg-white rounded-[24px] shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in border border-black/5">
	            <div className="p-8 sm:p-10 text-center">
              <div className="w-20 h-20 bg-rose-50 text-rose-500 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-sm border border-rose-100">
                <span className="material-symbols-outlined text-4xl">error</span>
              </div>
              <h2 className="text-3xl font-black text-zinc-900 tracking-tight">
                {errorModal.title}
              </h2>
              <p className="text-zinc-500 font-medium mt-3 leading-relaxed">
                {errorModal.message}
              </p>

              <div className="mt-8 flex justify-center">
                <Button
                  type="button"
                  onClick={() => setErrorModal(null)}
                  className="h-12 px-6 bg-zinc-900 text-white font-black rounded-md hover:bg-black transition-all shadow-sm"
                >
                  Entendi
                </Button>
              </div>
	            </div>
	          </div>
	        </div>
	      )}

	      {showPasswordRecoveryModal && (
	        <div className="fixed inset-0 z-[140] flex items-center justify-center p-4">
	          <div
	            className="absolute inset-0 bg-zinc-900/60 backdrop-blur-md"
	            onClick={() => {
	              setShowPasswordRecoveryModal(false);
	              clearRecoveryUrl();
	            }}
	          ></div>
	          <div className="relative bg-white rounded-[24px] shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in border border-black/5">
	            <div className="p-8 sm:p-10">
	              <div className="text-center">
	                <div className="w-20 h-20 bg-blue-50 text-[#0061FF] rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-sm border border-blue-100">
	                  <span className="material-symbols-outlined text-4xl">lock_reset</span>
	                </div>
	                <h2 className="text-3xl font-black text-zinc-900 tracking-tight">Definir nova senha</h2>
	                <p className="text-zinc-500 font-medium mt-3 leading-relaxed">
	                  Para concluir a recuperação, informe sua nova senha de acesso.
	                </p>
	              </div>

	              <div className="mt-8 space-y-4">
	                <div className="space-y-2">
	                  <p className="text-[10px] font-black text-zinc-500 uppercase tracking-widest">Nova senha</p>
	                  <Input
	                    type="password"
	                    value={recoveryPassword}
	                    onChange={(e) => setRecoveryPassword(e.target.value)}
	                    placeholder="Digite a nova senha"
	                    className="h-12 rounded-md bg-zinc-100/70 border border-black/5 font-bold text-zinc-800"
	                  />
	                </div>

	                <div className="space-y-2">
	                  <p className="text-[10px] font-black text-zinc-500 uppercase tracking-widest">Confirmar senha</p>
	                  <Input
	                    type="password"
	                    value={recoveryPasswordConfirm}
	                    onChange={(e) => setRecoveryPasswordConfirm(e.target.value)}
	                    placeholder="Repita a nova senha"
	                    className="h-12 rounded-md bg-zinc-100/70 border border-black/5 font-bold text-zinc-800"
	                  />
	                </div>

	                {recoveryMessage?.text ? (
	                  <div className={cn(
	                    "rounded-xl border px-4 py-3 text-sm font-semibold",
	                    recoveryMessage.type === 'success'
	                      ? "bg-green-50 border-green-200 text-green-700"
	                      : "bg-rose-50 border-rose-200 text-rose-700"
	                  )}>
	                    {recoveryMessage.text}
	                  </div>
	                ) : null}
	              </div>

	              <div className="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-3">
	                <Button
	                  type="button"
	                  variant="outline"
	                  onClick={() => {
	                    setShowPasswordRecoveryModal(false);
	                    clearRecoveryUrl();
	                  }}
	                  className="h-12 rounded-md font-black border-black/10 hover:bg-zinc-50"
	                  disabled={recoveryIsSaving}
	                >
	                  Agora não
	                </Button>
	                <Button
	                  type="button"
	                  onClick={submitRecoveryPassword}
	                  className="h-12 rounded-md font-black bg-[#0061FF] hover:bg-blue-700 text-white shadow-sm"
	                  disabled={recoveryIsSaving}
	                >
	                  <span className={cn("material-symbols-outlined mr-2 text-[18px]", recoveryIsSaving ? "animate-spin" : "")}>
	                    {recoveryIsSaving ? "progress_activity" : "save"}
	                  </span>
	                  {recoveryIsSaving ? 'Salvando...' : 'Salvar nova senha'}
	                </Button>
	              </div>
	            </div>
	          </div>
	        </div>
	      )}
	      {showWelcomeModal && importHistory.length > 0 && (
	        <div className="fixed inset-0 z-[120] flex items-center justify-center p-4">
	          <div className="absolute inset-0 bg-zinc-900/60 backdrop-blur-md" onClick={() => setShowWelcomeModal(false)}></div>
	          <div className="relative bg-white rounded-md shadow-2xl w-full max-w-lg p-10 text-center animate-fade-in border border-black/5">
             <div className="w-20 h-20 bg-gradient-to-br from-blue-400 to-blue-500 text-white rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-xl shadow-blue-500/20">
               <span className="material-symbols-outlined text-4xl">waving_hand</span>
             </div>
             <h2 className="text-3xl font-sans font-black text-zinc-900 mb-2 tracking-tight">
                Olá, {session?.user?.email?.split('@')[0] || 'Usuário'}!
             </h2>
             <p className="text-zinc-500 font-medium mb-8">
                Seja bem-vindo de volta ao Dataweaver.
             </p>
             
             <div className="bg-zinc-50 rounded-md border border-black/5 p-6 mb-8 text-left">
                <p className="text-[10px] font-black uppercase text-zinc-400 tracking-widest mb-4">Última Importação Registrada</p>
                <div className="mb-5">
                   <span className="text-zinc-800 font-sans font-black text-xl tracking-tight">
                     {importHistory[0]?.timestamp ? (
                       (() => {
                         const dateObj = new Date(importHistory[0].timestamp.replace(/-/g, '/'));
                         return dateObj.toLocaleString('pt-BR', { 
                           day: '2-digit', 
                           month: '2-digit', 
                           year: 'numeric',
                           hour: '2-digit',
                           minute: '2-digit'
                         }).replace(',', ' às');
                       })()
                     ) : 'Sem registros'}
                   </span>
                </div>
                <div className="grid grid-cols-2 gap-4">
                   <div className="bg-white p-4 rounded-md border border-black/5 flex flex-col justify-center">
                      <p className="text-[10px] text-zinc-400 font-black uppercase tracking-widest mb-1">Total</p>
                      <p className="text-2xl font-black text-zinc-800">{importHistory[0]?.total || 0}</p>
                   </div>
                   <div className="bg-blue-50 p-4 rounded-md border border-blue-100/50 flex flex-col justify-center">
                      <p className="text-[10px] text-blue-500 font-black uppercase tracking-widest mb-1">Novos</p>
                      <p className="text-2xl font-black text-[#0052cc]">{importHistory[0]?.added || 0}</p>
                   </div>
                </div>
             </div>

             <Button 
                onClick={() => setShowWelcomeModal(false)}
                className="w-full h-14 bg-gradient-to-r from-zinc-800 to-zinc-900 text-white font-black rounded-md hover:scale-[1.02] shadow-xl shadow-zinc-900/10 transition-all text-[15px] border-0"
             >
                Acessar Dashboard
             </Button>
          </div>
        </div>
      )}

      {showHistoryDetailModal && selectedHistoryItem && (
        <div className="fixed inset-0 z-[120] overflow-y-auto">
          <div
            className="absolute inset-0 bg-zinc-900/60 backdrop-blur-md"
            onClick={() => setShowHistoryDetailModal(false)}
          ></div>

          <div className="relative min-h-full flex items-start sm:items-center justify-center p-4 sm:p-8">
            <div className="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden animate-fade-in border border-zinc-200 max-h-[calc(100vh-2rem)] sm:max-h-[calc(100vh-4rem)] flex flex-col">
              <div className="p-6 sm:p-10 shrink-0">
                <div className="flex items-center justify-between pb-6 border-b border-zinc-100">
                  <div className="text-left">
                    <h2 className="text-3xl font-black text-zinc-900 tracking-tight">Detalhes da Importação</h2>
                    <p className="text-zinc-400 font-bold text-[11px] uppercase tracking-[0.2em] mt-2 flex items-center gap-2">
                      <span className="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                      {new Date(selectedHistoryItem.timestamp.replace(/-/g, '/')).toLocaleString('pt-BR')}
                    </p>
                  </div>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setShowHistoryDetailModal(false)}
                    className="rounded-full hover:bg-zinc-100 h-12 w-12"
                  >
                    <span className="material-symbols-outlined text-zinc-400">close</span>
                  </Button>
                </div>

                <div className="grid grid-cols-2 gap-4 sm:gap-6 mt-8">
                  <div className="bg-zinc-50 p-5 sm:p-6 rounded-2xl border border-zinc-100 flex flex-col items-start shadow-sm">
                    <p className="text-[11px] text-zinc-400 font-bold uppercase tracking-widest mb-2">Novos Registros</p>
                    <p className="text-4xl font-black text-zinc-900 leading-none">{selectedHistoryItem.added || 0}</p>
                  </div>
                  <div className="bg-blue-50 p-5 sm:p-6 rounded-2xl border border-blue-100 flex flex-col items-start text-left shadow-sm">
                    <p className="text-[11px] text-blue-500 font-bold uppercase tracking-widest mb-2">Atualizados</p>
                    <p className="text-4xl font-black text-[#0052cc] leading-none">{selectedHistoryItem.updated || 0}</p>
                  </div>
                </div>
              </div>

              <div className="px-6 sm:px-10 pb-6 sm:pb-8 flex-1 min-h-0 overflow-y-auto pr-3 custom-scrollbar text-left">
                {selectedHistoryItem.patients && selectedHistoryItem.patients.length > 0 ? (
                  <div className="space-y-3 pt-1">
                    {selectedHistoryItem.patients.map((p, i) => (
                      <div key={i} className="flex items-center justify-between p-4 bg-white rounded-xl border border-zinc-100 hover:border-blue-200 hover:shadow-md transition-all group">
                        <div className="flex items-center gap-4 min-w-0">
                          <div className="text-[10px] font-mono text-zinc-300 w-6 text-right font-black shrink-0">
                            {String(i + 1).padStart(2, '0')}.
                          </div>
                          <div className={`w-10 h-10 rounded-lg flex items-center justify-center font-black text-[11px] ${p.type === 'update' ? 'bg-blue-100 text-blue-600' : 'bg-emerald-100 text-emerald-600'} shadow-sm uppercase shrink-0`}>
                            {(p.nome && p.nome.length >= 2) ? p.nome.substring(0, 2).toUpperCase() : '??'}
                          </div>
                          <div className="min-w-0">
                            <p className="font-black text-zinc-800 text-[14px] group-hover:text-blue-600 transition-colors uppercase truncate max-w-[320px] sm:max-w-[380px]">
                              {p.nome || 'REGISTRO SEM NOME'}
                            </p>
                            <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-tight mt-0.5">
                              {p.type === 'update' ? 'Dados Enriquecidos / Atualizados' : 'Novo Paciente Cadastrado'}
                            </p>
                          </div>
                        </div>
                        <span className="px-3 py-1 bg-zinc-50 border border-zinc-200 rounded-md text-[10px] text-zinc-400 font-black tracking-tighter shrink-0">
                          ID: {p.id}
                        </span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="py-14 text-center bg-zinc-50 rounded-2xl border border-dashed border-zinc-200">
                    <span className="material-symbols-outlined text-zinc-300 text-6xl mb-4 opacity-50">inventory_2</span>
                    <p className="text-zinc-500 font-black text-lg">Resumo detalhado indisponível</p>
                  </div>
                )}
              </div>

              <div className="px-6 sm:px-10 py-4 border-t border-zinc-100 bg-white shrink-0">
                <Button
                  onClick={() => setShowHistoryDetailModal(false)}
                  className="w-full h-14 sm:h-16 bg-blue-600 text-white font-black rounded-xl hover:bg-blue-700 shadow-xl shadow-blue-500/20 transition-all text-[15px] sm:text-lg uppercase tracking-tight"
                >
                  Fechar Visualização
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {showDbfDrawer && selectedDbfRecord && (
        <div className="fixed inset-0 z-[110]">
          <button
            type="button"
            aria-label="Fechar detalhe lateral"
            onClick={closeDbfDrawer}
            className="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm"
          />
          <aside className="absolute right-0 top-0 h-full w-full max-w-[540px] bg-white shadow-2xl border-l border-black/5 flex flex-col animate-in slide-in-from-right duration-300">
            <div className="p-6 sm:p-8 border-b border-black/5 bg-gradient-to-br from-zinc-50 to-white">
              <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-zinc-400 mb-2">Painel lateral do paciente</p>
                  <h3 className="text-3xl font-black text-zinc-900 leading-tight truncate">
                    {selectedDbfRecord.displayName || 'REGISTRO SEM NOME'}
                  </h3>
                  <p className="text-zinc-500 font-medium mt-2">
                    Linha {selectedDbfRecord.rowNumber} • NCAD {formatDbfFieldValue(selectedDbfRecord.record, 0)}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={closeDbfDrawer}
                  className="w-11 h-11 rounded-full bg-white border border-black/5 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50 flex items-center justify-center shrink-0 shadow-sm"
                  aria-label="Fechar painel lateral"
                >
                  <span className="material-symbols-outlined text-[20px]">close</span>
                </button>
              </div>

              <div className="mt-5 flex flex-wrap gap-2">
                <span className="px-3 py-1.5 rounded-full bg-blue-50 text-[#0061FF] text-[10px] font-black uppercase tracking-widest border border-blue-100">
                  {dbfViewMode === 'complete' ? 'Campos completos' : 'Visão compacta'}
                </span>
                <span className={cn(
                  "px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border",
                  dbfDrawerMode === 'edit'
                    ? "bg-amber-50 text-amber-700 border-amber-200"
                    : "bg-zinc-50 text-zinc-500 border-black/5"
                )}>
                  {dbfDrawerMode === 'edit' ? 'Editando' : 'Visualizando'}
                </span>
                <span className="px-3 py-1.5 rounded-full bg-zinc-50 text-zinc-500 text-[10px] font-black uppercase tracking-widest border border-black/5">
                  Clique fora para fechar
                </span>
              </div>
            </div>

            <div className="flex-1 overflow-y-auto custom-scrollbar p-5 sm:p-6 space-y-4">
              {dbfDrawerMode === 'edit' ? (
                <div className="space-y-4">
                  <div className="rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-amber-700 mb-1">Edição</p>
                    <p className="text-sm text-zinc-700 font-medium">
                      Alterações são aplicadas com backup automático e só quando houver diferença real.
                    </p>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div className="rounded-2xl bg-white border border-black/5 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Nome do Paciente</p>
                      <input
                        value={dbfDraft?.[1] ?? ''}
                        onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 1: e.target.value }))}
                        maxLength={38}
                        className="w-full bg-zinc-50 border border-black/5 rounded-xl px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                      />
                      <p className="text-[10px] font-bold text-zinc-400 mt-1">Máx. 38 caracteres</p>
                    </div>
                    <div className="rounded-2xl bg-white border border-black/5 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Responsável</p>
                      <input
                        value={dbfDraft?.[2] ?? ''}
                        onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 2: e.target.value }))}
                        maxLength={33}
                        className="w-full bg-zinc-50 border border-black/5 rounded-xl px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                      />
                    </div>
                    <div className="rounded-2xl bg-white border border-black/5 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Documento</p>
                      <input
                        value={dbfDraft?.[7] ?? ''}
                        onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 7: e.target.value }))}
                        maxLength={20}
                        className="w-full bg-zinc-50 border border-black/5 rounded-xl px-3 py-2 text-sm font-bold text-zinc-900 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                      />
                    </div>
                    <div className="rounded-2xl bg-white border border-black/5 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">CEP (Resid.)</p>
                      <input
                        value={dbfDraft?.[6] ?? ''}
                        onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 6: e.target.value }))}
                        maxLength={9}
                        className="w-full bg-zinc-50 border border-black/5 rounded-xl px-3 py-2 text-sm font-bold text-zinc-900 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                      />
                    </div>
                  </div>

                  <details open className="rounded-2xl border border-black/5 bg-white shadow-sm overflow-hidden">
                    <summary className="list-none cursor-pointer px-4 py-4 flex items-center justify-between gap-4 bg-zinc-50/70 hover:bg-zinc-50 transition-colors">
                      <div className="flex items-center gap-3">
                        <span className="material-symbols-outlined text-[#0061FF]">home</span>
                        <p className="text-sm font-black text-zinc-900 uppercase tracking-widest">Residencial</p>
                      </div>
                      <span className="material-symbols-outlined text-zinc-400">expand_more</span>
                    </summary>
                    <div className="p-4 border-t border-black/5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Endereço</p>
                        <input
                          value={dbfDraft?.[3] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 3: e.target.value }))}
                          maxLength={38}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Cidade</p>
                        <input
                          value={dbfDraft?.[4] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 4: e.target.value }))}
                          maxLength={25}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">UF</p>
                        <input
                          value={dbfDraft?.[5] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 5: e.target.value.toUpperCase() }))}
                          maxLength={2}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">CEP</p>
                        <input
                          value={dbfDraft?.[6] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 6: e.target.value }))}
                          maxLength={9}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                    </div>
                  </details>

                  <details className="rounded-2xl border border-black/5 bg-white shadow-sm overflow-hidden">
                    <summary className="list-none cursor-pointer px-4 py-4 flex items-center justify-between gap-4 bg-zinc-50/70 hover:bg-zinc-50 transition-colors">
                      <div className="flex items-center gap-3">
                        <span className="material-symbols-outlined text-[#0061FF]">business</span>
                        <p className="text-sm font-black text-zinc-900 uppercase tracking-widest">Comercial</p>
                      </div>
                      <span className="material-symbols-outlined text-zinc-400">expand_more</span>
                    </summary>
                    <div className="p-4 border-t border-black/5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Endereço Comercial</p>
                        <input
                          value={dbfDraft?.[8] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 8: e.target.value }))}
                          maxLength={38}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Cidade Comercial</p>
                        <input
                          value={dbfDraft?.[9] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 9: e.target.value }))}
                          maxLength={25}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">UF Comercial</p>
                        <input
                          value={dbfDraft?.[10] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 10: e.target.value.toUpperCase() }))}
                          maxLength={2}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">CEP Comercial</p>
                        <input
                          value={dbfDraft?.[11] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 11: e.target.value }))}
                          maxLength={9}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                    </div>
                  </details>

                  <details className="rounded-2xl border border-black/5 bg-white shadow-sm overflow-hidden">
                    <summary className="list-none cursor-pointer px-4 py-4 flex items-center justify-between gap-4 bg-zinc-50/70 hover:bg-zinc-50 transition-colors">
                      <div className="flex items-center gap-3">
                        <span className="material-symbols-outlined text-[#0061FF]">description</span>
                        <p className="text-sm font-black text-zinc-900 uppercase tracking-widest">Complementos</p>
                      </div>
                      <span className="material-symbols-outlined text-zinc-400">expand_more</span>
                    </summary>
                    <div className="p-4 border-t border-black/5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Envio Boleto</p>
                        <input
                          value={dbfDraft?.[12] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 12: e.target.value }))}
                          maxLength={20}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                      <div className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">DR/DRA</p>
                        <input
                          value={dbfDraft?.[13] ?? ''}
                          onChange={(e) => setDbfDraft((prev) => ({ ...(prev || {}), 13: e.target.value }))}
                          maxLength={20}
                          className="w-full bg-white border border-black/5 rounded-lg px-3 py-2 text-sm font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                      </div>
                    </div>
                  </details>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                    <Button
                      variant="outline"
                      type="button"
                      onClick={() => {
                        setDbfDrawerMode('view');
                        setDbfDraft(null);
                      }}
                      disabled={dbfIsSaving}
                      className="h-12 rounded-xl font-black border-black/10 hover:bg-zinc-50"
                    >
                      Cancelar
                    </Button>
                    <Button
                      type="button"
                      onClick={saveDbfEdits}
                      disabled={dbfIsSaving}
                      className="h-12 rounded-xl font-black bg-[#0061FF] hover:bg-blue-700 text-white shadow-sm"
                    >
                      <span className={cn("material-symbols-outlined mr-2 text-[18px]", dbfIsSaving ? "animate-spin" : "")}>
                        {dbfIsSaving ? 'progress_activity' : 'save'}
                      </span>
                      {dbfIsSaving ? 'Salvando...' : 'Salvar'}
                    </Button>
                  </div>
                </div>
              ) : (
                <>
                  <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-2xl bg-zinc-50 border border-black/5 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Responsável</p>
                      <p className="text-sm font-bold text-zinc-900">{formatDbfFieldValue(selectedDbfRecord.record, 2)}</p>
                    </div>
                    <div className="rounded-2xl bg-zinc-50 border border-black/5 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Documento</p>
                      <p className="text-sm font-bold text-zinc-900 font-mono">{formatDbfDoc(selectedDbfRecord.record?.[7])}</p>
                    </div>
                    <div className="rounded-2xl bg-zinc-50 border border-black/5 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">CEP</p>
                      <p className="text-sm font-bold text-zinc-900 font-mono">{formatDbfCep(selectedDbfRecord.record?.[6])}</p>
                    </div>
                    <div className="rounded-2xl bg-zinc-50 border border-black/5 p-4">
                      <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Cidade / UF</p>
                      <p className="text-sm font-bold text-zinc-900">{formatDbfCityUf(selectedDbfRecord.record?.[4], selectedDbfRecord.record?.[5])}</p>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <Button
                      type="button"
                      onClick={() => {
                        setDbfDrawerMode('edit');
                        setDbfDraft(buildDbfDraftFromRecord(selectedDbfRecord.record));
                      }}
                      className="h-12 rounded-xl font-black bg-[#0061FF] hover:bg-blue-700 text-white shadow-sm"
                    >
                      <span className="material-symbols-outlined mr-2 text-[18px]">edit</span>
                      Editar
                    </Button>
                    <Button
                      variant="outline"
                      type="button"
                      onClick={closeDbfDrawer}
                      className="h-12 rounded-xl font-black border-black/10 hover:bg-zinc-50"
                    >
                      Fechar
                    </Button>
                  </div>

                  <div className="rounded-2xl border border-blue-100 bg-blue-50/40 p-4">
                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#0061FF] mb-1">Modo de exibição</p>
                    <p className="text-sm text-zinc-700 font-medium">
                      O painel lateral reúne todos os campos sem poluir a grade principal. Em modo completo, a tabela também mostra colunas extras.
                    </p>
                  </div>

                  <div className="space-y-3">
                    {dbfDetailGroups.map((group, groupIndex) => (
                      <details
                        key={group.title}
                        open={dbfViewMode === 'complete' || groupIndex === 0}
                        className="group rounded-2xl border border-black/5 bg-white shadow-sm overflow-hidden"
                      >
                        <summary className="list-none cursor-pointer px-4 py-4 flex items-center justify-between gap-4 bg-zinc-50/70 hover:bg-zinc-50 transition-colors">
                          <div className="flex items-center gap-3 min-w-0">
                            <div className="w-10 h-10 rounded-xl bg-white border border-black/5 text-[#0061FF] flex items-center justify-center shrink-0 shadow-sm">
                              <span className="material-symbols-outlined text-[20px]">{group.icon}</span>
                            </div>
                            <div className="min-w-0">
                              <p className="text-sm font-black text-zinc-900 uppercase tracking-widest">{group.title}</p>
                              <p className="text-[11px] font-medium text-zinc-400 mt-0.5">{group.description}</p>
                            </div>
                          </div>
                          <span className="material-symbols-outlined text-zinc-400 transition-transform duration-200">
                            expand_more
                          </span>
                        </summary>
                        <div className="p-4 border-t border-black/5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                          {group.fields.map((field) => (
                            <div key={field.label} className="rounded-xl bg-zinc-50 border border-black/5 p-3">
                              <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">{field.label}</p>
                              <p className="text-sm font-bold text-zinc-900 break-words">
                                {field.value(selectedDbfRecord.record)}
                              </p>
                            </div>
                          ))}
                        </div>
                      </details>
                    ))}
                  </div>
                </>
              )}
            </div>
          </aside>
        </div>
      )}
    </div>
  );
}

export default App;
