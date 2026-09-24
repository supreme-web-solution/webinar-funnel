import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[10px] text-sm font-medium transition-[background-color,box-shadow,border-color,transform,color] duration-150 ease-out disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-[#3B82F6]/35 focus-visible:ring-offset-2 aria-invalid:ring-destructive/20 aria-invalid:border-destructive active:scale-[0.98]",
  {
    variants: {
      variant: {
        default: "btn-brand",
        brand: "btn-brand",
        "brand-outline":
          "btn-brand-outline",
        "brand-outline-on-dark":
          "btn-brand-outline-on-dark active:scale-[0.98]",
        destructive:
          "border border-transparent bg-destructive text-white shadow-sm hover:bg-destructive/90",
        outline:
          "border border-[#E2E8F0] bg-white text-[#334155] shadow-[0_1px_2px_rgba(15,23,42,0.04)] hover:border-[#BFDBFE] hover:bg-[#F8FAFC] hover:text-[#1E293B]",
        secondary:
          "border border-transparent bg-[#F1F5F9] text-[#334155] shadow-none hover:bg-[#E2E8F0]",
        ghost:
          "border border-transparent text-[#475569] shadow-none hover:bg-[#EFF6FF] hover:text-[#2563EB] active:scale-100",
        link: "border-0 text-[#2563EB] shadow-none underline-offset-4 hover:underline active:scale-100",
      },
      size: {
        "default": "h-10 px-4 has-[>svg]:px-3.5",
        "sm": "h-9 gap-1.5 px-3.5 text-xs has-[>svg]:px-2.5",
        "lg": "h-11 px-5 text-[0.9375rem] has-[>svg]:px-4",
        "icon": "size-10",
        "icon-sm": "size-9",
        "icon-lg": "size-11",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
