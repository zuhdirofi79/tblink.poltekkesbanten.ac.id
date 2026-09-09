import axios from 'axios'
import { Info, RefreshCcw } from 'lucide-react'
import React, { useEffect, useState } from 'react'

export default function DsPendamping() {
    const [pasien, setPasien] = useState()
    const [pasienLoading, setPasienLoading] = useState(false)
    useEffect(() => {
        setPasienLoading(true)
        axios.get(route('ds-data.pendamping.index'))
            .then(({ data }) => setPasien(data))
            .finally(() => setPasienLoading(false))
    }, [])

    return (
        <div className='my-3'>
            {pasienLoading ? <div className="flex justify-center items-center">
                <RefreshCcw className='animate-spin' size={40} />
            </div> :
                pasien ?
                    <p className='text-lg font-semibold text-primary flex gap-2 items-center'>
                        <Info size={20} />
                        <div>
                            Anda login sebagai Pendamping Pasien a/n <span className='text-destructive'>{pasien.nama}</span>
                        </div>
                    </p>
                    : ''
            }
        </div>
    )
}
