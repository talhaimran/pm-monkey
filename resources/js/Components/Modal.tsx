import { transition } from '@/motion';
import { Dialog, DialogPanel } from '@headlessui/react';
import { AnimatePresence, motion } from 'motion/react';
import { PropsWithChildren } from 'react';

/**
 * Design system dialog on Headless UI (focus trap, Esc, focus restore). Fades in and scales 96% → 100%;
 * under 760px it is a sheet that rises from the bottom.
 */
export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
    initialFocus,
}: PropsWithChildren<{
    show: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    closeable?: boolean;
    onClose: CallableFunction;
    initialFocus?: React.RefObject<HTMLElement | null>;
}>) {
    const maxWidthClass = { sm: 'md:max-w-sm', md: 'md:max-w-md', lg: 'md:max-w-lg', xl: 'md:max-w-xl', '2xl': 'md:max-w-2xl' }[maxWidth];
    const sheet = typeof window !== 'undefined' && window.matchMedia('(max-width: 759px)').matches;
    const hidden = sheet ? { opacity: 0, y: 90 } : { opacity: 0, scale: 0.96 };

    return (
        <AnimatePresence>
            {show && (
                <Dialog static open onClose={() => closeable && onClose()} initialFocus={initialFocus} className="fixed inset-0 z-50 flex items-end overflow-y-auto md:items-center md:p-6">
                    <motion.div
                        aria-hidden="true"
                        className="fixed inset-0 bg-ink/40"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1, transition: transition('dialogIn') }}
                        exit={{ opacity: 0, transition: transition('dialogOut') }}
                    />
                    <DialogPanel
                        as={motion.div}
                        initial={hidden}
                        animate={{ opacity: 1, scale: 1, y: 0, transition: transition(sheet ? 'drawerIn' : 'dialogIn') }}
                        exit={{ ...hidden, transition: transition(sheet ? 'drawerOut' : 'dialogOut') }}
                        className={`relative w-full overflow-hidden rounded-t-sheet bg-card text-text shadow-pop md:mx-auto md:rounded-card ${maxWidthClass}`}
                    >
                        {children}
                    </DialogPanel>
                </Dialog>
            )}
        </AnimatePresence>
    );
}
