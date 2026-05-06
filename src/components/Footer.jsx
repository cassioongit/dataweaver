import React from 'react';

const Footer = () => {
  return (
    <footer className="w-full min-h-14 bg-transparent border-t border-black/5 flex items-center justify-center px-4 sm:px-8 py-3 shrink-0 z-20">
      <div className="flex flex-col sm:flex-row items-center gap-1 sm:gap-2 text-center animate-fade-in">
        <span className="text-[10px] font-black text-zinc-300 uppercase tracking-[0.2em] pt-0.5">DESENVOLVIDO POR</span>
        <p className="text-[11px] text-zinc-500 font-bold tracking-tight">
          cassioalexandre <span className="text-zinc-400 font-medium">®</span> 2026
        </p>
      </div>
    </footer>
  );
};

export default Footer;
