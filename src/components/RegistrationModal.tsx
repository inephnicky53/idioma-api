'use client';

import React from 'react';
import { useRegistrationModal } from '@/contexts/RegistrationModalContext';
import ClubRegistrationForm from '@/components/ClubRegistrationForm';

export default function RegistrationModal() {
  const { isOpen, closeModal } = useRegistrationModal();

  if (!isOpen) return null;

  return (
    <ClubRegistrationForm onClose={closeModal} />
  );
}

